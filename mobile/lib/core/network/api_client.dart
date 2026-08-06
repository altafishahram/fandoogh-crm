import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/config/app_config.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:uuid/uuid.dart';

typedef ApiAccessHandler = void Function(ApiFailure failure);

final apiClientProvider = Provider<ApiClient>((ref) {
  final client = ApiClient();
  ref.onDispose(client.close);
  return client;
});

final class ApiClient {
  ApiClient({Dio? dio}) : _dio = dio ?? Dio(_options()) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          options.headers['X-Request-Id'] = const Uuid().v7();
          final currentToken = token;
          if (currentToken != null && currentToken.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $currentToken';
          }
          handler.next(options);
        },
        onError: (exception, handler) {
          final failure = ApiFailure.fromDioException(exception);
          if (failure.statusCode == 401 ||
              failure.code == 'AGENCY_INACTIVE' ||
              failure.code == 'PASSWORD_CHANGE_REQUIRED') {
            onAccessFailure?.call(failure);
          }
          handler.next(exception.copyWith(error: failure));
        },
      ),
    );
  }

  final Dio _dio;
  String? token;
  ApiAccessHandler? onAccessFailure;

  Dio get dio => _dio;

  void close() => _dio.close(force: true);

  static BaseOptions _options() => BaseOptions(
    baseUrl: AppConfig.apiBaseUrl,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 30),
    sendTimeout: const Duration(seconds: 30),
    headers: const <String, String>{'Accept': 'application/json'},
  );
}

ApiFailure apiFailureFrom(Object error) {
  if (error is ApiFailure) return error;
  if (error is DioException && error.error is ApiFailure) {
    return error.error! as ApiFailure;
  }
  if (error is DioException) return ApiFailure.fromDioException(error);
  return const ApiFailure(
    code: 'UNEXPECTED_ERROR',
    message: 'خطای پیش‌بینی‌نشده‌ای رخ داد.',
  );
}
