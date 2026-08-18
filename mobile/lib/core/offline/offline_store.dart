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
  String? _scope;

  bool get isConfigured => _scope != null;

  void configure(Map<String, dynamic> user) {
    final agency = user['agency'];
    final agencyId = agency is Map ? agency['id'] : null;
    final userId = user['id'];
    if (agencyId != null && userId != null) _scope = '${agencyId}_$userId';
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
  ) async {
    final current = <String, Map<String, dynamic>>{
      for (final item in await records(resource)) '${item['id']}': item,
    };
    for (final item in incoming) {
      current['${item['id']}'] = Map<String, dynamic>.from(item);
    }
    final values = current.values.toList(growable: false)
      ..sort(
        (a, b) =>
            '${b['updated_at'] ?? ''}'.compareTo('${a['updated_at'] ?? ''}'),
      );
    await _storage.write(
      key: _key('cache_$resource'),
      value: jsonEncode(values),
    );
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
    for (final suffix in <String>[
      'cache_properties',
      'cache_customers',
      'operations',
      'cursor_properties',
      'cursor_customers',
    ]) {
      await _storage.delete(key: _key(suffix));
    }
    _scope = null;
  }

  Future<void> _writeOperations(List<Map<String, dynamic>> values) =>
      _storage.write(key: _key('operations'), value: jsonEncode(values));

  String _key(String suffix) {
    final scope = _scope;
    if (scope == null) throw StateError('Offline store is not configured.');
    return 'melkban_${scope}_$suffix';
  }
}
