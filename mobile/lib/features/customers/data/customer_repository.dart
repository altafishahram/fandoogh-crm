import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

final customerRepositoryProvider = Provider<CustomerRepository>((ref) {
  ref.watch(matchIdentityProvider);
  return CustomerRepository(
    ref.watch(apiClientProvider),
    ref.watch(offlineStoreProvider).scoped(),
  );
});

final customersProvider =
    AsyncNotifierProvider<CustomersController, PagedResult<CustomerRecord>>(
      CustomersController.new,
    );

final customerProvider = FutureProvider.family<CustomerRecord, int>((ref, id) {
  ref.watch(matchSessionProvider);
  ref.watch(matchDataRevisionProvider);
  return ref.watch(customerRepositoryProvider).find(id);
});

final customerNotesProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(customerRepositoryProvider).notes(id),
    );

final customerHistoryProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(customerRepositoryProvider).history(id),
    );

final class CustomerRecord {
  const CustomerRecord(this.data);
  factory CustomerRecord.fromJson(Map<String, dynamic> json) =>
      CustomerRecord(json);

  final Map<String, dynamic> data;
  int get id => (data['id'] as num).toInt();
  String get name => '${data['full_name'] ?? ''}'.trim().isNotEmpty
      ? '${data['full_name']}'.trim()
      : '${data['first_name'] ?? ''} ${data['last_name'] ?? ''}'.trim();
  String get mobile => data['mobile'] as String? ?? '';
  String get status => data['status'] as String? ?? 'active';
  String get intent => data['intent'] as String? ?? 'buy';
  String get updatedAt => data['updated_at'] as String? ?? '';
  String get createdAt => data['created_at'] as String? ?? '';
  int get lockVersion => (data['lock_version'] as num?)?.toInt() ?? 1;
  bool get pendingSync => data['pending_sync'] == true;
  MatchSummary? get matchSummary =>
      MatchSummary.fromJson(data['match_summary']);
}

final class CustomersController
    extends AsyncNotifier<PagedResult<CustomerRecord>> {
  String _query = '';
  String? _status;
  String? _intent;
  String? _propertyType;
  String? _district;
  Map<String, Object?> _requirementFilters = const <String, Object?>{};
  String _order = 'newest';

  @override
  Future<PagedResult<CustomerRecord>> build() {
    ref.watch(matchSessionProvider);
    ref.watch(matchDataRevisionProvider);
    return _load(1);
  }

  Map<String, Object?> get activeFilters => <String, Object?>{
    'q': _query,
    'status': _status,
    'intent': _intent,
    'property_type': _propertyType,
    'district': _district,
    ..._requirementFilters,
    'order': _order,
  };

  Future<void> applyFilters({
    String? query,
    String? status,
    String? intent,
    String? propertyType,
    String? district,
    Map<String, Object?> requirements = const <String, Object?>{},
    String order = 'newest',
  }) async {
    _query = query?.trim() ?? '';
    _status = status;
    _intent = intent;
    _propertyType = propertyType;
    _district = district?.trim().isEmpty == true ? null : district?.trim();
    _requirementFilters = Map<String, Object?>.fromEntries(
      requirements.entries.where(
        (entry) => entry.value != null && entry.value != '',
      ),
    );
    _order = order;
    state = const AsyncLoading<PagedResult<CustomerRecord>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> applySaved(Map<String, dynamic> filters) => applyFilters(
    query: filters['q'] as String?,
    status: filters['status'] as String?,
    intent: filters['intent'] as String?,
    propertyType: filters['property_type'] as String?,
    district: filters['district'] as String?,
    requirements: Map<String, Object?>.fromEntries(
      filters.entries.where(
        (entry) => !<String>{
          'q',
          'status',
          'intent',
          'property_type',
          'district',
          'order',
        }.contains(entry.key),
      ),
    ),
    order: '${filters['order'] ?? 'newest'}',
  );

  Future<void> refresh() async =>
      state = await AsyncValue.guard(() => _load(1));

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore || state.isLoading) return;
    final next = await AsyncValue.guard(() => _load(current.currentPage + 1));
    if (next.hasValue) state = AsyncData(current.append(next.requireValue));
    if (next.hasError) state = next;
  }

  Future<PagedResult<CustomerRecord>> _load(int page) => ref
      .read(customerRepositoryProvider)
      .list(page: page, filters: activeFilters);
}

class CustomerRepository extends ApiRepository {
  const CustomerRepository(super.client, [this.offlineStore]);

  final OfflineStore? offlineStore;

  Future<PagedResult<CustomerRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async {
    try {
      final result = await getPage<CustomerRecord>(
        '/customers',
        query: <String, Object?>{...filters, 'page': page, 'per_page': 20},
        decode: CustomerRecord.fromJson,
      );
      await offlineStore?.mergeRecords(
        'customers',
        result.items.map((item) => item.data),
      );
      return result;
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      final records = await offlineStore!.records('customers');
      final filtered = records.where((item) => _matches(item, filters)).toList()
        ..sort((a, b) => _sort(a, b, '${filters['order'] ?? 'newest'}'));
      return PagedResult<CustomerRecord>(
        items: filtered.map(CustomerRecord.fromJson).toList(growable: false),
        currentPage: 1,
        lastPage: 1,
      );
    }
  }

  Future<CustomerRecord> find(int id) async {
    try {
      final item = await getOne('/customers/$id');
      await offlineStore?.mergeRecords('customers', <Map<String, dynamic>>[
        item,
      ]);
      return CustomerRecord.fromJson(item);
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      final item = (await offlineStore!.records(
        'customers',
      )).where((value) => (value['id'] as num?)?.toInt() == id).firstOrNull;
      if (item == null) rethrow;
      return CustomerRecord.fromJson(item);
    }
  }

  Future<CustomerRecord> create(Map<String, Object?> data) async {
    final operationKey = const Uuid().v7();
    try {
      final item = await post(
        '/customers',
        data,
        headers: <String, Object?>{'Idempotency-Key': operationKey},
      );
      await offlineStore?.mergeRecords('customers', <Map<String, dynamic>>[
        item,
      ]);
      return CustomerRecord.fromJson(item);
    } catch (error) {
      if (!_isNetwork(error) || offlineStore?.isConfigured != true) rethrow;
      final operation = await offlineStore!.enqueue(
        resource: 'customers',
        action: 'create',
        path: '/customers',
        body: data,
        operationKey: operationKey,
      );
      final now = DateTime.now().toUtc().toIso8601String();
      final local = <String, dynamic>{
        ...data,
        'id': -DateTime.now().millisecondsSinceEpoch,
        'status': 'active',
        'lock_version': 1,
        'created_at': now,
        'updated_at': now,
        'pending_sync': true,
        'offline_operation_id': operation['id'],
      };
      await offlineStore!.mergeRecords('customers', <Map<String, dynamic>>[
        local,
      ]);
      return CustomerRecord.fromJson(local);
    }
  }

  Future<CustomerRecord> update(int id, Map<String, Object?> data) async {
    final item = await patch('/customers/$id', data);
    if (offlineStore?.isConfigured == true) {
      await offlineStore!.mergeRecords('customers', <Map<String, dynamic>>[
        item,
      ]);
      await offlineStore!.replaceRecords(
        'related-matches',
        <Map<String, dynamic>>[],
      );
    }
    return CustomerRecord.fromJson(item);
  }

  Future<List<Map<String, dynamic>>> notes(int id) =>
      getList('/customers/$id/notes');
  Future<Map<String, dynamic>> addNote(int id, String body) =>
      post('/customers/$id/notes', <String, Object?>{'body': body});
  Future<void> deleteNote(int customerId, int noteId) =>
      delete('/customers/$customerId/notes/$noteId');
  Future<List<Map<String, dynamic>>> history(int id) =>
      getList('/customers/$id/history');

  static bool _isNetwork(Object error) {
    final failure = apiFailureFrom(error);
    return failure.code == 'NETWORK_UNAVAILABLE' ||
        failure.code == 'NETWORK_TIMEOUT';
  }

  static bool _matches(
    Map<String, dynamic> item,
    Map<String, Object?> filters,
  ) {
    for (final key in <String>['status', 'intent']) {
      final expected = filters[key];
      if (expected != null && expected != '' && '${item[key]}' != '$expected') {
        return false;
      }
    }
    final propertyType = filters['property_type'];
    if (propertyType != null &&
        propertyType != '' &&
        '${item['desired_property_type']}' != '$propertyType') {
      return false;
    }
    for (final key in ['province_id', 'county_id', 'city_id']) {
      final expected = filters[key];
      if (expected != null && '${item['desired_$key']}' != '$expected') {
        return false;
      }
    }
    for (final entry in <String, String>{
      'city': 'desired_city',
      'district': 'desired_district',
    }.entries) {
      final expected = '${filters[entry.key] ?? ''}'.trim().toLowerCase();
      if (expected.isNotEmpty &&
          !'${item[entry.value] ?? ''}'.toLowerCase().contains(expected)) {
        return false;
      }
    }
    final minArea = num.tryParse('${filters['area_min'] ?? ''}');
    final maxArea = num.tryParse('${filters['area_max'] ?? ''}');
    final itemMinArea = num.tryParse('${item['min_area_sqm'] ?? ''}');
    final itemMaxArea = num.tryParse('${item['max_area_sqm'] ?? ''}');
    if (minArea != null && (itemMinArea == null || itemMinArea < minArea)) {
      return false;
    }
    if (maxArea != null && (itemMaxArea == null || itemMaxArea > maxArea)) {
      return false;
    }
    final budgetMin = num.tryParse('${filters['budget_min'] ?? ''}');
    final budgetMax = num.tryParse('${filters['budget_max'] ?? ''}');
    final itemBudgetMin = num.tryParse('${item['budget_min'] ?? ''}');
    final itemBudgetMax = num.tryParse('${item['budget_max'] ?? ''}');
    if (budgetMin != null &&
        (itemBudgetMin == null || itemBudgetMin < budgetMin)) {
      return false;
    }
    if (budgetMax != null &&
        (itemBudgetMax == null || itemBudgetMax > budgetMax)) {
      return false;
    }
    for (final pair in <String, String>{
      'deposit_min': 'rental_deposit_min',
      'deposit_max': 'rental_deposit_max',
      'rent_min': 'rental_rent_min',
      'rent_max': 'rental_rent_max',
    }.entries) {
      final expected = num.tryParse('${filters[pair.key] ?? ''}');
      final actual = num.tryParse('${item[pair.value] ?? ''}');
      if (expected == null) continue;
      if (actual == null ||
          (pair.key.endsWith('_min') && actual < expected) ||
          (pair.key.endsWith('_max') && actual > expected)) {
        return false;
      }
    }
    final bedrooms = (item['min_bedrooms'] as num?)?.toInt();
    final bedroomsMin = int.tryParse('${filters['bedrooms_min'] ?? ''}');
    if (bedroomsMin != null && (bedrooms == null || bedrooms < bedroomsMin)) {
      return false;
    }
    final parking = (item['min_parking_spaces'] as num?)?.toInt();
    final parkingMin = int.tryParse('${filters['parking_min'] ?? ''}');
    if (parkingMin != null && (parking == null || parking < parkingMin)) {
      return false;
    }
    for (final key in <String>[
      'has_parking',
      'has_storage_room',
      'owner_resides',
      'has_elevator',
      'has_balcony',
      'has_master_bathroom',
      'has_loan',
      'is_exchangeable',
      'has_pool',
      'has_jacuzzi',
      'has_sauna',
      'has_water',
      'has_electricity',
      'has_gas',
      'accepts_rent_conversion',
    ]) {
      if (filters[key] == true && item[key] != true && item[key] != 1) {
        return false;
      }
    }
    final q = '${filters['q'] ?? ''}'.trim().toLowerCase();
    if (q.isEmpty) return true;
    return '${item['full_name'] ?? ''} ${item['first_name'] ?? ''} ${item['last_name'] ?? ''} ${item['mobile'] ?? ''}'
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
