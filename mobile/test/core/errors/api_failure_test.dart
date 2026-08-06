import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('maps the standard API error envelope', () {
    final request = RequestOptions(path: '/properties');
    final exception = DioException(
      requestOptions: request,
      response: Response<Object?>(
        requestOptions: request,
        statusCode: 422,
        data: <String, Object?>{
          'error': <String, Object?>{
            'code': 'VALIDATION_FAILED',
            'message': 'اطلاعات واردشده معتبر نیست.',
            'details': <String, Object?>{
              'title': <String>['واردکردن عنوان الزامی است.'],
            },
            'request_id': 'request-123',
          },
        },
      ),
      type: DioExceptionType.badResponse,
    );

    final failure = ApiFailure.fromDioException(exception);

    expect(failure.code, 'VALIDATION_FAILED');
    expect(failure.statusCode, 422);
    expect(failure.requestId, 'request-123');
    expect(failure.details, contains('title'));
  });

  test('maps connection errors without leaking transport details', () {
    final exception = DioException(
      requestOptions: RequestOptions(path: '/health'),
      type: DioExceptionType.connectionError,
    );

    final failure = ApiFailure.fromDioException(exception);

    expect(failure.code, 'NETWORK_UNAVAILABLE');
    expect(failure.message, 'ارتباط با سرور برقرار نشد.');
  });
}
