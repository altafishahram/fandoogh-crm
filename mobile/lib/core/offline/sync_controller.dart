import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final syncControllerProvider =
    AsyncNotifierProvider<SyncController, List<Map<String, dynamic>>>(
      SyncController.new,
    );

final class SyncController extends AsyncNotifier<List<Map<String, dynamic>>> {
  @override
  Future<List<Map<String, dynamic>>> build() async {
    final store = ref.watch(offlineStoreProvider);
    return store.isConfigured ? store.operations() : <Map<String, dynamic>>[];
  }

  Future<void> synchronize() async {
    final store = ref.read(offlineStoreProvider);
    if (!store.isConfigured || state.isLoading) return;
    state = const AsyncLoading<List<Map<String, dynamic>>>();
    try {
      await _push(store);
      await _pull(store, 'properties');
      await _pull(store, 'customers');
      state = AsyncData(await store.operations());
      ref.invalidate(propertiesProvider);
      ref.invalidate(customersProvider);
    } catch (error, stackTrace) {
      state = AsyncError(error, stackTrace);
    }
  }

  Future<void> refreshQueue() async {
    final store = ref.read(offlineStoreProvider);
    if (store.isConfigured) state = AsyncData(await store.operations());
  }

  Future<void> _push(OfflineStore store) async {
    final client = ref.read(apiClientProvider);
    for (final original in await store.operations()) {
      if (original['status'] == 'conflict') continue;
      final operation = Map<String, dynamic>.from(original)
        ..['status'] = 'sending'
        ..remove('message');
      await store.replaceOperation(operation);
      try {
        final body = operation['body'];
        final response = await client.dio.request<Map<String, dynamic>>(
          '${operation['path']}',
          data: body is Map ? Map<String, dynamic>.from(body) : body,
          options: Options(
            method: 'POST',
            headers: <String, Object?>{
              'Idempotency-Key': operation['operation_key'],
            },
          ),
        );
        final value = response.data?['data'];
        await store.completeOperation(
          operation,
          value is Map ? Map<String, dynamic>.from(value) : null,
        );
      } catch (error) {
        final failure = apiFailureFrom(error);
        operation['status'] = failure.statusCode == 409 ? 'conflict' : 'failed';
        operation['message'] = failure.statusCode == 409
            ? 'ثبت هم‌زمان اطلاعات امکان‌پذیر نیست؛ اطلاعات را تازه‌سازی و دوباره تلاش کنید.'
            : failure.message;
        if (_isNetwork(failure)) operation['status'] = 'pending';
        await store.replaceOperation(operation);
        if (_isNetwork(failure)) break;
      }
    }
  }

  Future<void> _pull(OfflineStore store, String resource) async {
    final client = ref.read(apiClientProvider);
    var cursor = await store.cursor(resource);
    var more = true;
    while (more) {
      final response = await client.dio.get<Map<String, dynamic>>(
        '/sync',
        queryParameters: <String, Object?>{
          'resource': resource,
          'after': cursor['after'],
          'after_id': cursor['after_id'],
          'per_page': 100,
        },
      );
      final envelope = response.data ?? <String, dynamic>{};
      final rawData = envelope['data'];
      final items = rawData is List
          ? rawData
                .whereType<Map>()
                .map(Map<String, dynamic>.from)
                .toList(growable: false)
          : <Map<String, dynamic>>[];
      await store.mergeRecords(resource, items);
      final meta = envelope['meta'];
      final values = meta is Map
          ? Map<String, dynamic>.from(meta)
          : <String, dynamic>{};
      more = values['has_more'] == true;
      cursor = <String, dynamic>{
        'after': values['next_after'],
        'after_id': values['next_after_id'],
      };
      if (cursor['after'] != null) await store.writeCursor(resource, cursor);
      if (items.isEmpty) break;
    }
  }

  static bool _isNetwork(ApiFailure failure) =>
      failure.code == 'NETWORK_UNAVAILABLE' ||
      failure.code == 'NETWORK_TIMEOUT';
}
