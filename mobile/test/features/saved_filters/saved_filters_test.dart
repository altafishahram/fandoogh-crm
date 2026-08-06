import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/features/saved_filters/presentation/saved_filters.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('saving a filter keeps dialog lifecycle valid', (tester) async {
    final adapter = _SavedFiltersAdapter();
    final dio = Dio(BaseOptions(baseUrl: 'https://example.test'))
      ..httpClientAdapter = adapter;
    final client = ApiClient(dio: dio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(client)],
        child: MaterialApp(
          home: Scaffold(
            appBar: AppBar(
              actions: [
                SavedFiltersButton(
                  module: 'properties',
                  currentFilters: const {'status': 'available'},
                  onApply: (_) {},
                ),
              ],
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.byTooltip('فیلترهای ذخیره‌شده'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('ذخیره فیلتر فعلی'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Phase4Physical');
    await tester.tap(find.text('ذخیره'));
    await tester.pumpAndSettle();

    expect(tester.takeException(), isNull);
    expect(adapter.postCount, 1);
    expect(find.text('Phase4Physical'), findsOneWidget);

    client.close();
  });
}

final class _SavedFiltersAdapter implements HttpClientAdapter {
  int postCount = 0;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (options.method == 'POST') postCount++;

    final filters = postCount == 0
        ? <Object?>[]
        : <Object?>[
            <String, Object?>{
              'id': 1,
              'module': 'properties',
              'name': 'Phase4Physical',
              'filters': <String, Object?>{'status': 'available'},
              'is_default': false,
            },
          ];
    final data = options.method == 'POST' ? filters.single : filters;

    return ResponseBody.fromString(
      jsonEncode(<String, Object?>{'data': data}),
      options.method == 'POST' ? 201 : 200,
      headers: <String, List<String>>{
        Headers.contentTypeHeader: <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
