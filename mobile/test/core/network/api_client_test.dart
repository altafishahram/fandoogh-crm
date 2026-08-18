import 'dart:convert';
import 'dart:io';
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

  test('reports offline failures and the next successful response', () async {
    final adapter = _ConnectivityAdapter();
    final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
    final changes = <bool>[];
    client.onConnectivityChanged = changes.add;

    await expectLater(
      client.dio.get<Object?>('/status'),
      throwsA(isA<DioException>()),
    );
    expect(changes, <bool>[false]);

    adapter.isOnline = true;
    await client.dio.get<Object?>('/status');
    expect(changes, <bool>[false, true]);
    client.close();
  });
}

final class _ConnectivityAdapter implements HttpClientAdapter {
  bool isOnline = false;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (!isOnline) {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
        error: const SocketException('offline'),
      );
    }
    return ResponseBody.fromString(
      '{}',
      200,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
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
