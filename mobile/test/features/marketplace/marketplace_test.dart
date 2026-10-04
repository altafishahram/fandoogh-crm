import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:fandoogh_crm/app/public_app.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:fandoogh_crm/features/chat/chat_repository.dart';
import 'package:fandoogh_crm/features/chat/chat_pages.dart';
import 'package:fandoogh_crm/features/marketplace/presentation/publication_page.dart';
import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:fandoogh_crm/features/marketplace/data/marketplace_repository.dart';
import 'package:fandoogh_crm/features/marketplace/presentation/marketplace_pages.dart';
import 'package:fandoogh_crm/features/public_auth/public_auth.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test(
    'listing decoder drops all private fields, including unexpected specs',
    () {
      final listing = Listing.fromJson({
        'id': 1,
        'title': 'آگهی',
        'neighborhood': 'محله',
        'owners': [
          {'name': 'secret'},
        ],
        'street_address': 'secret',
        'specifications': {
          'parking_spaces': 2,
          'owner_phone': 'secret',
          'gps_lat': 35,
        },
      });
      expect(listing.specifications, {'parking_spaces': 2});
      expect(listing.neighborhood, 'محله');
    },
  );
  test(
    'listing requests audience and IDs without CRM or offline requests',
    () async {
      final adapter = _Adapter();
      final client = _client(adapter);
      final repository = MarketplaceRepository(client);
      final page = await repository.listings('agency', {
        'province_id': 1,
        'county_id': 11,
        'city_id': 111,
      }, 2);
      expect(page.items.single.title, 'آگهی امن');
      expect(adapter.requests.single.path, '/marketplace/listings');
      expect(
        adapter.requests.single.queryParameters,
        containsPair('audience', 'agency'),
      );
      expect(
        adapter.requests.single.queryParameters,
        containsPair('city_id', 111),
      );
      client.close();
    },
  );
  testWidgets('province change immediately clears county and city', (
    tester,
  ) async {
    final client = _client(_Adapter());
    var region = const RegionSelection(
      provinceId: 1,
      countyId: 11,
      cityId: 111,
      cityName: 'شهر اول',
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(client),
          tokenStoreProvider.overrideWithValue(_Tokens()),
        ],
        child: MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (_, setState) => RegionFields(
                value: region,
                onChanged: (value) => setState(() => region = value),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<int>).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('استان دوم').last);
    await tester.pumpAndSettle();
    expect(region.provinceId, 2);
    expect(region.countyId, isNull);
    expect(region.cityId, isNull);
    expect(region.cityName, isNull);
    await tester.pumpWidget(const SizedBox());
    client.close();
  });
  testWidgets(
    'public login visibly shows configured code and never starts private CRM',
    (tester) async {
      final adapter = _Adapter();
      final client = _client(adapter);
      final tokens = _Tokens();
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            apiClientProvider.overrideWithValue(client),
            tokenStoreProvider.overrideWithValue(tokens),
          ],
          child: const PublicApp(),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.textContaining('۱۲۳۴۵۶'), findsOneWidget);
      expect(find.text('شماره موبایل'), findsOneWidget);
      await tester.enterText(find.byType(TextFormField).first, '۰۹۱۲۱۲۳۴۵۶۷');
      await tester.enterText(find.byType(TextFormField).last, '۱۲۳۴۵۶');
      await tester.tap(find.text('ورود'));
      await tester.pumpAndSettle();
      expect(find.text('آگهی امن'), findsOneWidget);
      expect(tokens.token, 'public-token');
      expect(
        adapter.requests.any(
          (r) =>
              r.path.startsWith('/auth/') ||
              r.path.startsWith('/properties') ||
              r.path.startsWith('/customers') ||
              r.path.startsWith('/sync'),
        ),
        false,
      );
      await tester.tap(find.byTooltip('حساب و خروج'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('خروج'));
      await tester.pumpAndSettle();
      expect(tokens.token, isNull);
      expect(client.token, isNull);
      expect(find.text('آگهی امن'), findsNothing);
      await tester.pumpWidget(const SizedBox());
      client.close();
    },
  );
  testWidgets('public empty list fits narrow high-text-scale dark screen', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(375, 720);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final client = _client(_Adapter(empty: true));
    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(client)],
        child: MaterialApp(
          theme: ThemeData.dark(),
          home: MediaQuery(
            data: const MediaQueryData(
              size: Size(375, 720),
              textScaler: TextScaler.linear(1.8),
            ),
            child: const MarketplacePage(publicOnly: true),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.textContaining('آگهی‌ای برای این منطقه'), findsOneWidget);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
    client.close();
  });
  test(
    'public expired token clears session without offline identity fallback',
    () async {
      final adapter = _Adapter(expired: true);
      final client = _client(adapter);
      final tokens = _Tokens()..token = 'old';
      final container = ProviderContainer(
        overrides: [
          apiClientProvider.overrideWithValue(client),
          tokenStoreProvider.overrideWithValue(tokens),
        ],
      );
      addTearDown(container.dispose);
      container.read(publicSessionProvider);
      await Future<void>.delayed(const Duration(milliseconds: 25));
      expect(container.read(publicSessionProvider).value, isNull);
      expect(tokens.token, isNull);
      expect(client.token, isNull);
      expect(adapter.requests.single.path, '/public/auth/me');
      client.close();
    },
  );
  testWidgets(
    'closed chat keeps text history and disables photo/text sending',
    (tester) async {
      final client = _client(_Adapter(closed: true));
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(client)],
          child: const MaterialApp(home: ConversationPage(id: 5)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('تاریخچه مجاز'), findsOneWidget);
      expect(tester.widget<TextField>(find.byType(TextField)).enabled, false);
      expect(
        tester
            .widget<IconButton>(
              find.byWidgetPredicate(
                (widget) =>
                    widget is IconButton && widget.tooltip == 'ارسال عکس',
              ),
            )
            .onPressed,
        isNull,
      );
      await tester.pumpWidget(const SizedBox());
      client.close();
    },
  );
  testWidgets(
    'chat reassignment denial clears previously visible message history',
    (tester) async {
      final adapter = _Adapter();
      final client = _client(adapter);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(client)],
          child: const MaterialApp(home: ConversationPage(id: 5)),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('تاریخچه مجاز'), findsOneWidget);
      adapter.denyMessages = true;
      await tester.pump(const Duration(seconds: 15));
      await tester.pumpAndSettle();
      expect(find.text('تاریخچه مجاز'), findsNothing);
      expect(tester.widget<TextField>(find.byType(TextField)).enabled, false);
      await tester.pumpWidget(const SizedBox());
      client.close();
    },
  );
  testWidgets(
    'publication first save independently selects public and sends version zero',
    (tester) async {
      final adapter = _Adapter();
      final client = _client(adapter);
      await tester.pumpWidget(
        ProviderScope(
          overrides: [apiClientProvider.overrideWithValue(client)],
          child: const MaterialApp(home: PublicationPage(propertyId: 1)),
        ),
      );
      await tester.pumpAndSettle();
      await tester.tap(find.text('انتشار برای عموم'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('ذخیره تنظیمات انتشار'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('ذخیره تنظیمات انتشار'));
      await tester.pumpAndSettle();
      final values =
          adapter.requests.lastWhere((r) => r.method == 'PUT').data as Map;
      expect(values['share_with_agencies'], false);
      expect(values['publish_public'], true);
      expect(values['expected_version'], 0);
      expect(values['image_ids'], isEmpty);
      await tester.pumpWidget(const SizedBox());
      client.close();
    },
  );
  test('chat photo upload carries same idempotency key for retry', () async {
    final adapter = _Adapter();
    final client = _client(adapter);
    final repository = ChatRepository(client);
    final directory = await Directory.systemTemp.createTemp(
      'marketplace-chat-test',
    );
    try {
      final image = File('${directory.path}/image.png');
      await image.writeAsBytes([137, 80, 78, 71]);
      await repository.send(
        5,
        requestId: 'test-uuid',
        body: 'عکس',
        imagePath: image.path,
      );
      final data = adapter.requests.last.data as FormData;
      expect(
        Map<String, String>.fromEntries(data.fields)['client_message_id'],
        'test-uuid',
      );
      expect(data.files.single.key, 'image');
      expect(
        adapter.requests.last.path,
        '/marketplace/conversations/5/messages',
      );
    } finally {
      await directory.delete(recursive: true);
      client.close();
    }
  });
}

ApiClient _client(_Adapter adapter) => ApiClient(
  dio: Dio(BaseOptions(baseUrl: 'https://crm.fandooghstudio.ir/api/v1'))
    ..httpClientAdapter = adapter,
);

final class _Tokens implements TokenStore {
  String? token;
  @override
  Future<String?> read() async => token;
  @override
  Future<void> write(String value) async {
    token = value;
  }

  @override
  Future<void> clear() async {
    token = null;
  }

  @override
  Future<Map<String, dynamic>?> readUser() async => null;
  @override
  Future<void> writeUser(Map<String, dynamic> user) async {}
}

final class _Adapter implements HttpClientAdapter {
  _Adapter({this.empty = false, this.expired = false, this.closed = false});
  final bool empty, expired;
  final bool closed;
  bool denyMessages = false;
  final requests = <RequestOptions>[];
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? stream,
    Future<void>? cancel,
  ) async {
    if (stream != null) await stream.drain<void>();
    requests.add(options);
    Object data = {};
    int code = 200;
    Map<String, Object?> meta = {};
    if (options.path == '/public/auth/config') {
      data = {'mode': 'fixed_code', 'temporary_code': '123456'};
    } else if (options.path == '/public/auth/login') {
      data = {
        'token': 'public-token',
        'user': {'id': 1, 'phone': '09121234567', 'phone_verified': false},
      };
    } else if (options.path == '/public/auth/me' && expired) {
      code = 401;
      return ResponseBody.fromString(
        jsonEncode({
          'error': {'code': 'UNAUTHENTICATED', 'message': 'expired'},
        }),
        code,
        headers: {
          Headers.contentTypeHeader: ['application/json'],
        },
      );
    } else if (options.path == '/locations/provinces') {
      data = [
        {'id': 1, 'name': 'استان اول'},
        {'id': 2, 'name': 'استان دوم'},
      ];
    } else if (options.path == '/locations/counties') {
      data = options.queryParameters['province_id'] == 1
          ? [
              {'id': 11, 'name': 'شهرستان اول', 'province_id': 1},
            ]
          : [
              {'id': 22, 'name': 'شهرستان دوم', 'province_id': 2},
            ];
    } else if (options.path == '/locations/cities') {
      data = [
        {'id': 111, 'name': 'شهر اول', 'county_id': 11},
      ];
    } else if (options.path == '/marketplace/listings') {
      data = empty
          ? []
          : [
              {
                'id': 1,
                'title': 'آگهی امن',
                'neighborhood': 'محله امن',
                'area_sqm': 100,
                'bedrooms': 2,
                'year_built': 1400,
                'agency': {'id': 1, 'name': 'آژانس'},
              },
            ];
      meta = {'current_page': 1, 'last_page': 1};
    } else if (options.path.endsWith('/publication')) {
      data = {
        'id': null,
        'share_with_agencies': false,
        'publish_public': false,
        'public_title': 'عنوان عمومی',
        'public_description': '',
        'image_ids': [],
        'responding_user_id': 1,
        'version': 0,
        'can_publish': true,
        'can_change_advisor': true,
        'advisors': [
          {'id': 1, 'name': 'مشاور'},
        ],
        'candidate_images': [],
      };
    } else if (options.path == '/marketplace/conversations') {
      data = [
        {
          'id': 5,
          'listing_id': 1,
          'title': 'آگهی امن',
          'can_send': !closed,
          'unread_count': 1,
        },
      ];
      meta = {'current_page': 1, 'last_page': 1};
    } else if (options.path.endsWith('/messages')) {
      if (options.method == 'GET' && denyMessages) {
        code = 403;
      }
      data = options.method == 'GET'
          ? [
              {'id': 1, 'body': 'تاریخچه مجاز', 'is_mine': false},
            ]
          : {'id': 1, 'body': 'عکس', 'is_mine': true};
      meta = {'current_page': 1, 'last_page': 1};
    }
    return ResponseBody.fromString(
      jsonEncode({'data': data, 'meta': meta}),
      code,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
