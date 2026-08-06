import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final customerRepositoryProvider = Provider<CustomerRepository>(
  (ref) => CustomerRepository(ref.watch(apiClientProvider)),
);

final customersProvider =
    AsyncNotifierProvider<CustomersController, PagedResult<CustomerRecord>>(
      CustomersController.new,
    );

final customerProvider = FutureProvider.family<CustomerRecord, int>((ref, id) {
  return ref.watch(customerRepositoryProvider).find(id);
});

final customerNotesProvider =
    FutureProvider.family<List<Map<String, dynamic>>, int>(
      (ref, id) => ref.watch(customerRepositoryProvider).notes(id),
    );

final class CustomerRecord {
  const CustomerRecord(this.data);
  factory CustomerRecord.fromJson(Map<String, dynamic> json) =>
      CustomerRecord(json);

  final Map<String, dynamic> data;
  int get id => (data['id'] as num).toInt();
  String get name =>
      '${data['first_name'] ?? ''} ${data['last_name'] ?? ''}'.trim();
  String get mobile => data['mobile'] as String? ?? '';
  String get status => data['status'] as String? ?? 'active';
  String get intent => data['intent'] as String? ?? 'buy';
  String get updatedAt => data['updated_at'] as String? ?? '';
}

final class CustomersController
    extends AsyncNotifier<PagedResult<CustomerRecord>> {
  String _query = '';
  String? _status;
  String? _intent;

  @override
  Future<PagedResult<CustomerRecord>> build() => _load(1);

  Map<String, Object?> get activeFilters => <String, Object?>{
    'q': _query,
    'status': _status,
    'intent': _intent,
  };

  Future<void> applyFilters({
    String? query,
    String? status,
    String? intent,
  }) async {
    _query = query?.trim() ?? '';
    _status = status;
    _intent = intent;
    state = const AsyncLoading<PagedResult<CustomerRecord>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> applySaved(Map<String, dynamic> filters) => applyFilters(
    query: filters['q'] as String?,
    status: filters['status'] as String?,
    intent: filters['intent'] as String?,
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
  const CustomerRepository(super.client);

  Future<PagedResult<CustomerRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) => getPage<CustomerRecord>(
    '/customers',
    query: <String, Object?>{...filters, 'page': page, 'per_page': 20},
    decode: CustomerRecord.fromJson,
  );

  Future<CustomerRecord> find(int id) async =>
      CustomerRecord.fromJson(await getOne('/customers/$id'));
  Future<CustomerRecord> create(Map<String, Object?> data) async =>
      CustomerRecord.fromJson(await post('/customers', data));
  Future<CustomerRecord> update(int id, Map<String, Object?> data) async =>
      CustomerRecord.fromJson(await patch('/customers/$id', data));
  Future<List<Map<String, dynamic>>> notes(int id) =>
      getList('/customers/$id/notes');
  Future<Map<String, dynamic>> addNote(int id, String body) =>
      post('/customers/$id/notes', <String, Object?>{'body': body});
  Future<void> deleteNote(int customerId, int noteId) =>
      delete('/customers/$customerId/notes/$noteId');
}
