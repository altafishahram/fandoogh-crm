import 'package:dio/dio.dart';

final class ApiFailure implements Exception {
  const ApiFailure({
    required this.code,
    required this.message,
    this.statusCode,
    this.requestId,
    this.details = const <String, Object?>{},
  });

  factory ApiFailure.fromDioException(DioException exception) {
    final responseData = exception.response?.data;
    final payload = responseData is Map<String, dynamic>
        ? responseData
        : const <String, dynamic>{};
    final errorValue = payload['error'];
    final error = errorValue is Map<String, dynamic>
        ? errorValue
        : const <String, dynamic>{};
    final detailsValue = error['details'];

    final code = error['code'] as String? ?? _fallbackCode(exception);
    final serverMessage = error['message'] as String?;

    return ApiFailure(
      code: code,
      message: _persianMessage(code, serverMessage),
      statusCode: exception.response?.statusCode,
      requestId:
          error['request_id'] as String? ??
          exception.response?.headers.value('x-request-id'),
      details: detailsValue is Map<String, dynamic>
          ? Map<String, Object?>.unmodifiable(detailsValue)
          : const <String, Object?>{},
    );
  }

  final String code;
  final String message;
  final int? statusCode;
  final String? requestId;
  final Map<String, Object?> details;

  static String _fallbackCode(DioException exception) =>
      switch (exception.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => 'NETWORK_TIMEOUT',
        DioExceptionType.connectionError => 'NETWORK_UNAVAILABLE',
        _ => 'UNEXPECTED_ERROR',
      };

  static String _persianMessage(String code, String? serverMessage) =>
      switch (code) {
        'BAD_REQUEST' => 'ساختار درخواست معتبر نیست.',
        'UNAUTHENTICATED' => 'برای ادامه دوباره وارد حساب خود شوید.',
        'FORBIDDEN' => 'اجازه انجام این عملیات را ندارید.',
        'AGENCY_INACTIVE' => 'دسترسی آژانس غیرفعال شده است.',
        'PASSWORD_CHANGE_REQUIRED' =>
          'پیش از ادامه باید رمز عبور خود را تغییر دهید.',
        'RESOURCE_NOT_FOUND' => 'اطلاعات درخواستی پیدا نشد.',
        'DOMAIN_CONFLICT' => 'این عملیات با وضعیت فعلی اطلاعات سازگار نیست.',
        'STALE_RECORD' =>
          'این رکورد هم‌زمان تغییر کرده است؛ اطلاعات را تازه‌سازی کنید.',
        'FILE_TOO_LARGE' => 'حجم فایل بیشتر از حد مجاز است.',
        'UNSUPPORTED_MEDIA_TYPE' => 'نوع فایل تصویری پشتیبانی نمی‌شود.',
        'VALIDATION_FAILED' => 'اطلاعات واردشده معتبر نیست.',
        'INVALID_FILTER' => 'فیلتر انتخاب‌شده معتبر نیست.',
        'RATE_LIMITED' =>
          'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد تلاش کنید.',
        'NETWORK_TIMEOUT' => 'زمان انتظار پاسخ سرور تمام شد.',
        'NETWORK_UNAVAILABLE' => 'ارتباط با سرور برقرار نشد.',
        'INTERNAL_ERROR' || 'UNEXPECTED_ERROR' =>
          'خطای پیش‌بینی‌نشده‌ای رخ داد؛ لطفاً دوباره تلاش کنید.',
        _
            when serverMessage != null &&
                RegExp(r'[\u0600-\u06FF]').hasMatch(serverMessage) =>
          serverMessage,
        _ => 'انجام درخواست ممکن نشد؛ لطفاً دوباره تلاش کنید.',
      };

  @override
  String toString() => 'ApiFailure(code: $code, requestId: $requestId)';
}
