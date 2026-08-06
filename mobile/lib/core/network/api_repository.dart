import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';

class ApiRepository {
  const ApiRepository(this.client);

  final ApiClient client;

  Future<Map<String, dynamic>> getOne(String path) async {
    final response = await client.dio.get<Map<String, dynamic>>(path);
    return unwrapData(response.data);
  }

  Future<List<Map<String, dynamic>>> getList(
    String path, {
    Map<String, Object?>? query,
  }) async {
    final response = await client.dio.get<Map<String, dynamic>>(
      path,
      queryParameters: compact(query),
    );
    return unwrapList(response.data);
  }

  Future<PagedResult<T>> getPage<T>(
    String path, {
    Map<String, Object?>? query,
    required T Function(Map<String, dynamic>) decode,
  }) async {
    final response = await client.dio.get<Map<String, dynamic>>(
      path,
      queryParameters: compact(query),
    );
    return PagedResult.fromEnvelope<T>(
      response.data ?? <String, dynamic>{},
      decode,
    );
  }

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, Object?> body,
  ) async {
    final response = await client.dio.post<Map<String, dynamic>>(
      path,
      data: body,
    );
    return unwrapData(response.data);
  }

  Future<Map<String, dynamic>> patch(
    String path,
    Map<String, Object?> body,
  ) async {
    final response = await client.dio.patch<Map<String, dynamic>>(
      path,
      data: body,
    );
    return unwrapData(response.data);
  }

  Future<void> delete(String path) => client.dio.delete<void>(path);

  Future<Map<String, dynamic>> uploadImage(
    String path,
    String filePath, {
    ProgressCallback? onProgress,
  }) async {
    final form = FormData.fromMap(<String, Object?>{
      'image': await MultipartFile.fromFile(filePath),
    });
    final response = await client.dio.post<Map<String, dynamic>>(
      path,
      data: form,
      onSendProgress: onProgress,
    );
    return unwrapData(response.data);
  }

  static Map<String, dynamic> unwrapData(Map<String, dynamic>? envelope) {
    final value = envelope?['data'];
    return value is Map
        ? Map<String, dynamic>.from(value)
        : <String, dynamic>{};
  }

  static List<Map<String, dynamic>> unwrapList(Map<String, dynamic>? envelope) {
    final value = envelope?['data'];
    return value is List
        ? value
              .whereType<Map>()
              .map(Map<String, dynamic>.from)
              .toList(growable: false)
        : <Map<String, dynamic>>[];
  }

  static Map<String, Object?> compact(Map<String, Object?>? values) =>
      Map<String, Object?>.fromEntries(
        (values ?? const <String, Object?>{}).entries.where(
          (entry) => entry.value != null && entry.value != '',
        ),
      );
}
