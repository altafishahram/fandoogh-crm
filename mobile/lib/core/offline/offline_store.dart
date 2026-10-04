import 'dart:convert';

import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

final offlineStoreProvider = Provider<OfflineStore>(
  (ref) => OfflineStore(ref.watch(secureStorageProvider)),
);

final class OfflineStore {
  OfflineStore(this._storage);

  final FlutterSecureStorage _storage;
  static final _cacheMutations = <String, Future<void>>{};
  String? _scope;

  bool get isConfigured => _scope != null;
  String? get scopeKey => _scope;

  /// Capture the tenant/user before asynchronous requests or detail exit.
  OfflineStore scoped() => OfflineStore(_storage).._scope = _scope;

  void configure(Map<String, dynamic> user) {
    final agency = user['agency'];
    final agencyId = agency is Map ? agency['id'] : null;
    final userId = user['id'];
    _scope = agencyId != null && userId != null ? '${agencyId}_$userId' : null;
  }

  Future<List<Map<String, dynamic>>> records(String resource) async {
    final value = await _storage.read(key: _key('cache_$resource'));
    if (value == null) return <Map<String, dynamic>>[];
    final decoded = jsonDecode(value);
    return decoded is List
        ? decoded
              .whereType<Map>()
              .map(Map<String, dynamic>.from)
              .toList(growable: false)
        : <Map<String, dynamic>>[];
  }

  Future<void> mergeRecords(
    String resource,
    Iterable<Map<String, dynamic>> incoming,
  ) => updateRecords(resource, (records) {
    final current = <String, Map<String, dynamic>>{
      for (final item in records) '${item['id']}': item,
    };
    for (final item in incoming) {
      current['${item['id']}'] = Map<String, dynamic>.from(item);
    }
    records
      ..clear()
      ..addAll(current.values);
    records.sort(
      (a, b) =>
          '${b['updated_at'] ?? ''}'.compareTo('${a['updated_at'] ?? ''}'),
    );
  });

  Future<void> replaceRecords(
    String resource,
    Iterable<Map<String, dynamic>> records,
  ) {
    final incoming = records.toList(growable: false);
    return updateRecords(
      resource,
      (values) => values
        ..clear()
        ..addAll(incoming),
    );
  }

  /// Serialize cache mutations across scoped snapshots. A late read response
  /// must not overwrite a higher notification version.
  Future<void> updateRecords(
    String resource,
    void Function(List<Map<String, dynamic>>) update,
  ) {
    final store = scoped();
    final key = store._key('cache_$resource');
    final previous = _cacheMutations[key] ?? Future<void>.value();
    final next = previous.catchError((Object _) {}).then((_) async {
      final values = (await store.records(resource)).toList();
      update(values);
      await _storage.write(key: key, value: jsonEncode(values));
    });
    _cacheMutations[key] = next;
    return next.whenComplete(() {
      if (identical(_cacheMutations[key], next)) _cacheMutations.remove(key);
    });
  }

  /// An offline eligibility change cannot be re-ranked locally. Retire its
  /// affected snapshot instead of pretending stale or unknown matches are zero.
  Future<void> invalidateMatchData() async {
    final store = scoped();
    await store.replaceRecords('related-matches', <Map<String, dynamic>>[]);
    for (final resource in <String>['properties', 'customers']) {
      final records = await store.records(resource);
      for (final record in records) {
        record['match_summary'] = null;
      }
      await store.replaceRecords(resource, records);
    }
    final dashboards = await store.records('dashboard');
    for (final dashboard in dashboards) {
      final properties = dashboard['recent_properties'];
      if (properties is List) {
        for (final property in properties.whereType<Map>()) {
          property['match_summary'] = null;
        }
      }
    }
    await store.replaceRecords('dashboard', dashboards);
  }

  Future<void> completeOperation(
    Map<String, dynamic> operation,
    Map<String, dynamic>? serverRecord,
  ) async {
    final resource = '${operation['resource']}';
    final values = (await records(resource))
        .where((item) => item['offline_operation_id'] != operation['id'])
        .toList();
    if (serverRecord != null && serverRecord.isNotEmpty) {
      values.removeWhere((item) => '${item['id']}' == '${serverRecord['id']}');
      values.add(serverRecord);
    }
    values.sort(
      (a, b) =>
          '${b['updated_at'] ?? ''}'.compareTo('${a['updated_at'] ?? ''}'),
    );
    await _storage.write(
      key: _key('cache_$resource'),
      value: jsonEncode(values),
    );
    await removeOperation('${operation['id']}');
  }

  Future<Map<String, dynamic>> enqueue({
    required String resource,
    required String action,
    required String path,
    required Map<String, Object?> body,
    int? recordId,
    int? baseVersion,
    String? operationKey,
  }) async {
    final item = <String, dynamic>{
      'id': const Uuid().v7(),
      'operation_key': operationKey ?? const Uuid().v7(),
      'resource': resource,
      'action': action,
      'path': path,
      'body': body,
      'record_id': recordId,
      'base_version': baseVersion,
      'created_at': DateTime.now().toUtc().toIso8601String(),
      'status': 'pending',
    };
    final values = await operations()
      ..add(item);
    await _writeOperations(values);
    return item;
  }

  Future<List<Map<String, dynamic>>> operations() async {
    final value = await _storage.read(key: _key('operations'));
    if (value == null) return <Map<String, dynamic>>[];
    final decoded = jsonDecode(value);
    return decoded is List
        ? decoded.whereType<Map>().map(Map<String, dynamic>.from).toList()
        : <Map<String, dynamic>>[];
  }

  Future<int> pendingCount() async =>
      (await operations()).where((item) => item['status'] != 'synced').length;

  Future<void> replaceOperation(Map<String, dynamic> operation) async {
    final values = await operations();
    final index = values.indexWhere((item) => item['id'] == operation['id']);
    if (index >= 0) values[index] = operation;
    await _writeOperations(values);
  }

  Future<void> removeOperation(String id) async {
    final values = await operations()
      ..removeWhere((item) => item['id'] == id);
    await _writeOperations(values);
  }

  Future<Map<String, dynamic>> cursor(String resource) async {
    final value = await _storage.read(key: _key('cursor_$resource'));
    if (value == null) return <String, dynamic>{};
    final decoded = jsonDecode(value);
    return decoded is Map
        ? Map<String, dynamic>.from(decoded)
        : <String, dynamic>{};
  }

  Future<void> writeCursor(String resource, Map<String, dynamic> cursor) =>
      _storage.write(key: _key('cursor_$resource'), value: jsonEncode(cursor));

  Future<void> clearScope() async {
    if (_scope == null) return;
    final originalScope = _scope;
    final store = scoped();
    for (final suffix in <String>[
      'cache_properties',
      'cache_customers',
      'cache_match-notifications',
      'cache_related-matches',
      'cache_dashboard',
      'operations',
      'cursor_properties',
      'cursor_customers',
    ]) {
      await _storage.delete(key: store._key(suffix));
    }
    if (_scope == originalScope) _scope = null;
  }

  Future<void> _writeOperations(List<Map<String, dynamic>> values) =>
      _storage.write(key: _key('operations'), value: jsonEncode(values));

  String _key(String suffix) {
    final scope = _scope;
    if (scope == null) throw StateError('Offline store is not configured.');
    return 'melkban_${scope}_$suffix';
  }
}
