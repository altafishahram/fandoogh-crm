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

  String get displayMessage {
    if (code != 'VALIDATION_FAILED' || details.isEmpty) return message;

    final fields = details.keys.map(_fieldLabel).toSet().join('، ');
    return fields.isEmpty ? message : '$message\nموارد نیازمند بررسی: $fields';
  }

  static String _fieldLabel(String field) =>
      <String, String>{
        'title': 'عنوان ملک',
        'full_name': 'نام کامل',
        'mobile': 'شماره همراه',
        'phone': 'تلفن ثابت',
        'property_type': 'نوع ملک',
        'transaction_type': 'نوع معامله',
        'desired_property_type': 'نوع ملک موردنظر',
        'area_sqm': 'مساحت',
        'min_area_sqm': 'حداقل متراژ',
        'max_area_sqm': 'حداکثر متراژ',
        'sale_price': 'مبلغ کل فروش',
        'sale_price_per_sqm': 'مبلغ هر متر',
        'deposit_amount': 'ودیعه',
        'monthly_rent': 'اجاره ماهانه',
        'rental_deposit_min': 'حداقل ودیعه',
        'rental_deposit_max': 'حداکثر ودیعه',
        'rental_rent_min': 'حداقل اجاره',
        'rental_rent_max': 'حداکثر اجاره',
        'city': 'شهر',
        'desired_city': 'شهر موردنظر',
        'street_address': 'نشانی',
        'plaque': 'شماره پلاک نشانی',
        'delivery_status': 'وضعیت تخلیه',
        'available_from': 'تاریخ آماده تحویل',
        'evacuation_date': 'تاریخ تخلیه',
        'building_type': 'نوع بنا',
        'land_area_min': 'حداقل متراژ زمین',
        'land_area_max': 'حداکثر متراژ زمین',
        'building_area_min': 'حداقل متراژ بنا',
        'building_area_max': 'حداکثر متراژ بنا',
        'owner.full_name': 'نام کامل مالک',
        'owner.mobile': 'شماره همراه مالک',
        'expected_version': 'نسخه اطلاعات',
      }[field] ??
      field;

  static String _fallbackCode(DioException exception) =>
      switch (exception.type) {
        DioExceptionType.connectionTimeout ||
        DioExceptionType.sendTimeout ||
        DioExceptionType.receiveTimeout => 'NETWORK_TIMEOUT',
        DioExceptionType.connectionError => 'NETWORK_UNAVAILABLE',
        _ => 'UNEXPECTED_ERROR',
      };

  static String _persianMessage(
    String code,
    String? serverMessage,
  ) => switch (code) {
    'BAD_REQUEST' => 'ساختار درخواست معتبر نیست.',
    'UNAUTHENTICATED' => 'برای ادامه دوباره وارد حساب خود شوید.',
    'FORBIDDEN' => 'اجازه انجام این عملیات را ندارید.',
    'AGENCY_INACTIVE' => 'دسترسی آژانس غیرفعال شده است.',
    'PASSWORD_CHANGE_REQUIRED' =>
      'پیش از ادامه باید رمز عبور خود را تغییر دهید.',
    'RESOURCE_NOT_FOUND' => 'اطلاعات درخواستی پیدا نشد.',
    'DOMAIN_CONFLICT' => 'این عملیات با وضعیت فعلی اطلاعات سازگار نیست.',
    'STALE_RECORD' =>
      'ثبت هم‌زمان اطلاعات امکان‌پذیر نیست؛ اطلاعات را تازه‌سازی و دوباره تلاش کنید.',
    'FILE_TOO_LARGE' => 'حجم فایل بیشتر از حد مجاز است.',
    'UNSUPPORTED_MEDIA_TYPE' => 'نوع فایل تصویری پشتیبانی نمی‌شود.',
    'VALIDATION_FAILED' => 'اطلاعات واردشده معتبر نیست.',
    'INVALID_FILTER' => 'فیلتر انتخاب‌شده معتبر نیست.',
    'RATE_LIMITED' => 'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد تلاش کنید.',
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
