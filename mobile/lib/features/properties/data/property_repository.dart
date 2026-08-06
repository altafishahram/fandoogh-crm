import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final propertyRepositoryProvider = Provider<PropertyRepository>(
  (ref) => PropertyRepository(ref.watch(apiClientProvider)),
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

  @override
  Future<PagedResult<PropertyRecord>> build() => _load(1);

  Map<String, Object?> get activeFilters => <String, Object?>{
    'q': _query,
    'status': _status,
    'property_type': _type,
    'transaction_type': _transaction,
  };

  Future<void> applyFilters({
    String? query,
    String? status,
    String? type,
    String? transaction,
  }) async {
    _query = query?.trim() ?? '';
    _status = status;
    _type = type;
    _transaction = transaction;
    state = const AsyncLoading<PagedResult<PropertyRecord>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> applySaved(Map<String, dynamic> filters) => applyFilters(
    query: filters['q'] as String?,
    status: filters['status'] as String?,
    type: filters['property_type'] as String?,
    transaction: filters['transaction_type'] as String?,
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
  const PropertyRepository(super.client);

  Future<PagedResult<PropertyRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) => getPage<PropertyRecord>(
    '/properties',
    query: <String, Object?>{...filters, 'page': page, 'per_page': 20},
    decode: PropertyRecord.fromJson,
  );

  Future<PropertyRecord> find(int id) async =>
      PropertyRecord.fromJson(await getOne('/properties/$id'));

  Future<PropertyRecord> create(Map<String, Object?> data) async =>
      PropertyRecord.fromJson(await post('/properties', data));

  Future<PropertyRecord> update(int id, Map<String, Object?> data) async =>
      PropertyRecord.fromJson(await patch('/properties/$id', data));

  Future<PropertyRecord> changeStatus(
    int id, {
    required String status,
    required String expectedUpdatedAt,
    String? reason,
  }) async => PropertyRecord.fromJson(
    await post('/properties/$id/status', <String, Object?>{
      'status': status,
      'reason': reason,
      'expected_updated_at': expectedUpdatedAt,
    }),
  );

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
  Future<void> deleteImage(int propertyId, int imageId) =>
      delete('/properties/$propertyId/images/$imageId');
}
