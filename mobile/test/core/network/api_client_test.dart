import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('adds bearer token and reports authentication expiry', () async {
    final dio = Dio()..httpClientAdapter = _ExpiryAdapter();
    final client = ApiClient(dio: dio)..token = 'secret-token';
    ApiFailure? accessFailure;
    client.onAccessFailure = (failure) => accessFailure = failure;

    await expectLater(
      client.dio.get<Object?>('/private'),
      throwsA(isA<DioException>()),
    );

    expect(_ExpiryAdapter.lastHeaders['Authorization'], 'Bearer secret-token');
    expect(accessFailure?.statusCode, 401);
    expect(accessFailure?.code, 'UNAUTHENTICATED');
    client.close();
  });
}

final class _ExpiryAdapter implements HttpClientAdapter {
  static Map<String, dynamic> lastHeaders = <String, dynamic>{};

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    lastHeaders = options.headers;
    return ResponseBody.fromString(
      jsonEncode(<String, Object?>{
        'error': <String, Object?>{
          'code': 'UNAUTHENTICATED',
          'message': 'Authentication is required.',
        },
      }),
      401,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
