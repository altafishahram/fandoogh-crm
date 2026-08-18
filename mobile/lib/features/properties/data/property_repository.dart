import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

final propertyRepositoryProvider = Provider<PropertyRepository>(
  (ref) => PropertyRepository(
    ref.watch(apiClientProvider),
    ref.watch(offlineStoreProvider),
  ),
);

final propertiesProvider =
    AsyncNotifierProvider<PropertiesController, PagedResult<PropertyRecord>>(
      PropertiesController.new,
    );

final propertyProvider = FutureProvider.family<PropertyRecord, int>((
  ref,
  id,
) async {
  return ref.watch(propertyRepositoryProvider).find(id);
});

final propertyNotesProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(propertyRepositoryProvider).notes(id),
    );

final propertyHistoryProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(propertyRepositoryProvider).history(id),
    );

final propertyImagesProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(propertyRepositoryProvider).images(id),
    );

final class PropertyRecord {
  const PropertyRecord(this.data);
  factory PropertyRecord.fromJson(Map<String, dynamic> json) =>
      PropertyRecord(json);

  final Map<String, dynamic> data;
  int get id => (data['id'] as num).toInt();
  String get title => data['title'] as String? ?? 'بدون عنوان';
  String get code => data['code'] as String? ?? '';
  String get status => data['status'] as String? ?? 'available';
  String get propertyType => data['property_type'] as String? ?? 'other';
  String get transactionType => data['transaction_type'] as String? ?? 'sale';
  String get city => data['city'] as String? ?? '';
  String get address => data['street_address'] as String? ?? '';
  String get updatedAt => data['updated_at'] as String? ?? '';
  String get createdAt => data['created_at'] as String? ?? '';
  int get lockVersion => (data['lock_version'] as num?)?.toInt() ?? 1;
  bool get pendingSync => data['pending_sync'] == true;
  List<Map<String, dynamic>> get owners {
    final value = data['owners'];
    return value is List
        ? value
              .whereType<Map>()
              .map(Map<String, dynamic>.from)
              .toList(growable: false)
        : <Map<String, dynamic>>[];
  }
}

final class PropertiesController
    extends AsyncNotifier<PagedResult<PropertyRecord>> {
  String _query = '';
  String? _status;
  String? _type;
  String? _transaction;
  String? _district;
  String? _areaMin;
  String? _areaMax;
  String? _priceMin;
  String? _priceMax;
  String? _bedroomsMin;
  String _order = 'newest';

  @override
  Future<PagedResult<PropertyRecord>> build() => _load(1);

  Map<String, Object?> get activeFilters => <String, Object?>{
    'q': _query,
    'status': _status,
    'property_type': _type,
    'transaction_type': _transaction,
    'district': _district,
    'area_min': _areaMin,
    'area_max': _areaMax,
    'price_min': _priceMin,
    'price_max': _priceMax,
    'bedrooms_min': _bedroomsMin,
    'order': _order,
  };

  Future<void> applyFilters({
    String? query,
    String? status,
    String? type,
    String? transaction,
    String? district,
    String? areaMin,
    String? areaMax,
    String? priceMin,
    String? priceMax,
    String? bedroomsMin,
    String order = 'newest',
  }) async {
    _query = query?.trim() ?? '';
    _status = status;
    _type = type;
    _transaction = transaction;
    _district = district?.trim().isEmpty == true ? null : district?.trim();
    _areaMin = areaMin?.trim().isEmpty == true ? null : areaMin?.trim();
    _areaMax = areaMax?.trim().isEmpty == true ? null : areaMax?.trim();
    _priceMin = priceMin?.trim().isEmpty == true ? null : priceMin?.trim();
    _priceMax = priceMax?.trim().isEmpty == true ? null : priceMax?.trim();
    _bedroomsMin = bedroomsMin?.trim().isEmpty == true
        ? null
        : bedroomsMin?.trim();
    _order = order;
    state = const AsyncLoading<PagedResult<PropertyRecord>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> applySaved(Map<String, dynamic> filters) => applyFilters(
    query: filters['q'] as String?,
    status: filters['status'] as String?,
    type: filters['property_type'] as String?,
    transaction: filters['transaction_type'] as String?,
    district: filters['district'] as String?,
    areaMin: '${filters['area_min'] ?? ''}',
    areaMax: '${filters['area_max'] ?? ''}',
    priceMin: '${filters['price_min'] ?? ''}',
    priceMax: '${filters['price_max'] ?? ''}',
    bedroomsMin: '${filters['bedrooms_min'] ?? ''}',
    order: '${filters['order'] ?? 'newest'}',
  );

  Future<void> refresh() async {
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || state.isLoading) return;
    final next = await AsyncValue.guard(() => _load(current.currentPage + 1));
    if (next.hasValue) state = AsyncData(current.append(next.requireValue));
    if (next.hasError) state = next;
  }

  Future<PagedResult<PropertyRecord>> _load(int page) => ref
      .read(propertyRepositoryProvider)
      .list(page: page, filters: activeFilters);
}

class PropertyRepository extends ApiRepository {
  const PropertyRepository(super.client, [this.offlineStore]);

  final OfflineStore? offlineStore;

  Future<PagedResult<PropertyRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async {
    try {
      final result = await getPage<PropertyRecord>(
        '/properties',
        query: <String, Object?>{...filters, 'page': page, 'per_page': 20},
        decode: PropertyRecord.fromJson,
      );
      await offlineStore?.mergeRecords(
        'properties',
        result.items.map((item) => item.data),
      );
      return result;
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      final records = await offlineStore!.records('properties');
      final filtered = records.where((item) => _matches(item, filters)).toList()
        ..sort((a, b) => _sort(a, b, '${filters['order'] ?? 'newest'}'));
      return PagedResult<PropertyRecord>(
        items: filtered.map(PropertyRecord.fromJson).toList(growable: false),
        currentPage: 1,
        lastPage: 1,
      );
    }
  }

  Future<PropertyRecord> find(int id) async {
    try {
      final item = await getOne('/properties/$id');
      await offlineStore?.mergeRecords('properties', <Map<String, dynamic>>[
        item,
      ]);
      return PropertyRecord.fromJson(item);
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      final item = (await offlineStore!.records(
        'properties',
      )).where((value) => (value['id'] as num?)?.toInt() == id).firstOrNull;
      if (item == null) rethrow;
      return PropertyRecord.fromJson(item);
    }
  }

  Future<PropertyRecord> create(
    Map<String, Object?> data, {
    bool allowOffline = true,
  }) async {
    final operationKey = const Uuid().v7();
    try {
      final item = await post(
        '/properties',
        data,
        headers: <String, Object?>{'Idempotency-Key': operationKey},
      );
      await offlineStore?.mergeRecords('properties', <Map<String, dynamic>>[
        item,
      ]);
      return PropertyRecord.fromJson(item);
    } catch (error) {
      if (!allowOffline ||
          !_isNetwork(error) ||
          offlineStore?.isConfigured != true) {
        rethrow;
      }
      final operation = await offlineStore!.enqueue(
        resource: 'properties',
        action: 'create',
        path: '/properties',
        body: data,
        operationKey: operationKey,
      );
      final now = DateTime.now().toUtc().toIso8601String();
      final local = <String, dynamic>{
        ...data,
        'id': -DateTime.now().millisecondsSinceEpoch,
        'code': 'در انتظار همگام‌سازی',
        'status': data['status'] ?? 'available',
        'owners': data['owner'] == null
            ? <Object?>[]
            : <Object?>[data['owner']],
        'lock_version': 1,
        'created_at': now,
        'updated_at': now,
        'pending_sync': true,
        'offline_operation_id': operation['id'],
      };
      await offlineStore!.mergeRecords('properties', <Map<String, dynamic>>[
        local,
      ]);
      return PropertyRecord.fromJson(local);
    }
  }

  Future<PropertyRecord> update(int id, Map<String, Object?> data) async =>
      PropertyRecord.fromJson(await patch('/properties/$id', data));

  Future<PropertyRecord> changeStatus(
    int id, {
    required String status,
    required String expectedUpdatedAt,
    String? reason,
    int? expectedVersion,
  }) async {
    final body = <String, Object?>{
      'status': status,
      'reason': reason,
      if (expectedVersion case final version?)
        'expected_version': version
      else
        'expected_updated_at': expectedUpdatedAt,
    };
    final operationKey = const Uuid().v7();
    try {
      final item = await post(
        '/properties/$id/status',
        body,
        headers: <String, Object?>{'Idempotency-Key': operationKey},
      );
      await offlineStore?.mergeRecords('properties', <Map<String, dynamic>>[
        item,
      ]);
      return PropertyRecord.fromJson(item);
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      await offlineStore!.enqueue(
        resource: 'properties',
        action: 'status',
        path: '/properties/$id/status',
        body: body,
        recordId: id,
        baseVersion: expectedVersion,
        operationKey: operationKey,
      );
      final records = await offlineStore!.records('properties');
      final source = records
          .where((item) => (item['id'] as num?)?.toInt() == id)
          .firstOrNull;
      final local = <String, dynamic>{
        ...?source,
        'id': id,
        'status': status,
        'pending_sync': true,
      };
      await offlineStore!.mergeRecords('properties', <Map<String, dynamic>>[
        local,
      ]);
      return PropertyRecord.fromJson(local);
    }
  }

  Future<List<Map<String, dynamic>>> notes(int id) =>
      getList('/properties/$id/notes');
  Future<Map<String, dynamic>> addNote(int id, String body) =>
      post('/properties/$id/notes', <String, Object?>{'body': body});
  Future<void> deleteNote(int propertyId, int noteId) =>
      delete('/properties/$propertyId/notes/$noteId');
  Future<List<Map<String, dynamic>>> history(int id) =>
      getList('/properties/$id/history');
  Future<List<Map<String, dynamic>>> images(int id) =>
      getList('/properties/$id/images');
  Future<Map<String, dynamic>> addImage(
    int id,
    String path, {
    void Function(int, int)? onProgress,
  }) => uploadImage('/properties/$id/images', path, onProgress: onProgress);
  Future<Map<String, dynamic>> updateImage(
    int propertyId,
    int imageId, {
    required int sortOrder,
    required bool isCover,
    required String expectedUpdatedAt,
  }) => patch('/properties/$propertyId/images/$imageId', <String, Object?>{
    'sort_order': sortOrder,
    'is_cover': isCover,
    'expected_updated_at': expectedUpdatedAt,
  });
  Future<void> deleteImage(int propertyId, int imageId) =>
      delete('/properties/$propertyId/images/$imageId');

  static bool _isNetwork(Object error) {
    final failure = apiFailureFrom(error);
    return failure.code == 'NETWORK_UNAVAILABLE' ||
        failure.code == 'NETWORK_TIMEOUT';
  }

  static bool _matches(
    Map<String, dynamic> item,
    Map<String, Object?> filters,
  ) {
    for (final key in <String>[
      'status',
      'property_type',
      'transaction_type',
      'city',
      'district',
    ]) {
      final expected = filters[key];
      if (expected != null && expected != '' && '${item[key]}' != '$expected') {
        return false;
      }
    }
    final area = num.tryParse('${item['area_sqm'] ?? ''}');
    final areaMin = num.tryParse('${filters['area_min'] ?? ''}');
    final areaMax = num.tryParse('${filters['area_max'] ?? ''}');
    if (areaMin != null && (area == null || area < areaMin)) return false;
    if (areaMax != null && (area == null || area > areaMax)) return false;
    final priceField = item['transaction_type'] == 'rent'
        ? item['deposit_amount']
        : item['sale_price'];
    final price = num.tryParse('$priceField');
    final priceMin = num.tryParse('${filters['price_min'] ?? ''}');
    final priceMax = num.tryParse('${filters['price_max'] ?? ''}');
    if (priceMin != null && (price == null || price < priceMin)) return false;
    if (priceMax != null && (price == null || price > priceMax)) return false;
    final bedrooms = (item['bedrooms'] as num?)?.toInt();
    final bedroomsMin = int.tryParse('${filters['bedrooms_min'] ?? ''}');
    if (bedroomsMin != null && (bedrooms == null || bedrooms < bedroomsMin)) {
      return false;
    }
    final q = '${filters['q'] ?? ''}'.trim().toLowerCase();
    if (q.isEmpty) return true;
    final owners = item['owners'] is List
        ? (item['owners'] as List)
              .map(
                (owner) => owner is Map
                    ? '${owner['full_name'] ?? owner['display_name'] ?? ''}'
                    : '',
              )
              .join(' ')
        : '';
    return '${item['code'] ?? ''} ${item['title'] ?? ''} $owners'
        .toLowerCase()
        .contains(q);
  }

  static int _sort(
    Map<String, dynamic> a,
    Map<String, dynamic> b,
    String order,
  ) {
    final result = '${b['created_at'] ?? b['updated_at'] ?? ''}'.compareTo(
      '${a['created_at'] ?? a['updated_at'] ?? ''}',
    );
    return order == 'oldest' ? -result : result;
  }
}
