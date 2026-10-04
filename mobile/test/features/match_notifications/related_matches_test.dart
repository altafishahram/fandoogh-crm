import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/auth/auth_state.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:fandoogh_crm/features/match_notifications/data/related_matches_controller.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/match_notification_detail_page.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/related_match_badge.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/related_matches_page.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/properties/presentation/widgets/property_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  setUp(() => FlutterSecureStorage.setMockInitialValues(<String, String>{}));

  test(
    'old/offline summaries remain unknown; explicit empty is known',
    () async {
      expect(
        PropertyRecord.fromJson(<String, dynamic>{'id': 1}).matchSummary,
        isNull,
      );
      expect(
        CustomerRecord.fromJson(<String, dynamic>{
          'id': 2,
          'match_summary': null,
        }).matchSummary,
        isNull,
      );
      expect(MatchSummary.fromJson(<String, dynamic>{'count': 3}), isNull);
      expect(
        MatchSummary.fromJson(<String, dynamic>{
          'count': 0,
          'unread_count': 0,
        })?.count,
        0,
      );
      final store = _store();
      await store.mergeRecords('properties', <Map<String, dynamic>>[
        <String, dynamic>{'id': 1},
      ]);
      final adapter = _Adapter()..offline = true;
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      final property = await PropertyRepository(client, store).find(1);
      expect(property.matchSummary, isNull);
      final page = await MatchNotificationRepository(
        client,
        store,
      ).related(const RelatedMatchScope.property(1));
      expect(page.items, isEmpty);
      expect(page.total, isNull);
      expect(page.unreadCount, isNull);
      expect(page.fromCache, isTrue);
    },
  );

  test(
    'current API unread boolean overrides read_at from an older version',
    () {
      final item = MatchNotification.fromJson(<String, dynamic>{
        'id': 1,
        'version': 2,
        'is_read': false,
        'read_at': '2026-08-01T00:00:00Z',
        'short_reason': 'علت کوتاه',
        'match': <String, dynamic>{
          'property_rank': 1,
          'customer_rank': null,
          'financial_range': <String, dynamic>{
            'deposit_min': 1000,
            'rent_max': 500,
          },
        },
      });
      expect(item.isRead, isFalse);
      expect(item.propertyRank, 1);
      expect(item.customerRank, isNull);
      expect(item.matchedDepositMin, 1000);
      expect(item.matchedRentMax, 500);
      expect(
        MatchNotification.fromJson(item.toJson()).shortReason,
        'علت کوتاه',
      );
    },
  );

  test(
    'first ten then twenty maximum; exact membership survives offline',
    () async {
      final store = _store();
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      final repository = MatchNotificationRepository(client, store);
      final container = ProviderContainer(
        overrides: [
          matchNotificationRepositoryProvider.overrideWithValue(repository),
          matchSessionProvider.overrideWithValue('agency3-user7'),
          matchIdentityProvider.overrideWithValue('agency3-user7'),
        ],
      );
      addTearDown(container.dispose);
      const scope = RelatedMatchScope.property(91);
      final subscription = container.listen(
        relatedMatchesProvider(scope),
        (_, _) {},
      );
      addTearDown(subscription.close);
      final first = await container.read(relatedMatchesProvider(scope).future);
      expect(first.items.length, 10);
      expect(first.total, 20);
      expect(first.perPage, 10);
      expect(first.currentPage, 1);
      expect(first.hasMore, isTrue);
      await container.read(relatedMatchesProvider(scope).notifier).loadMore();
      final all = container.read(relatedMatchesProvider(scope)).requireValue;
      expect(
        all.items.map((item) => item.id),
        List<int>.generate(20, (index) => index + 1),
      );
      expect(all.hasMore, isFalse);
      expect(all.unreadCount, 20);
      await container.read(relatedMatchesProvider(scope).notifier).loadMore();
      expect(adapter.requests.length, 2);
      expect(
        adapter.requests.every((request) => request.method == 'GET'),
        isTrue,
      );
      expect(
        adapter.requests.map((request) => request.queryParameters['per_page']),
        everyElement(10),
      );
      await store.mergeRecords('match-notifications', <Map<String, dynamic>>[
        _notification(999),
      ]);
      adapter.offline = true;
      final offlineFirst = await repository.related(scope);
      final offlineSecond = await repository.related(scope, page: 2);
      expect(offlineFirst.items.length, 10);
      expect(offlineSecond.items.length, 10);
      expect(offlineSecond.items.any((item) => item.id == 999), isFalse);
      expect(offlineFirst.total, 20);
      expect(offlineFirst.fromCache, isTrue);
      expect(
        (await repository.related(const RelatedMatchScope.customer(27))).total,
        isNull,
      );
      expect(await store.pendingCount(), 0);
      await expectLater(
        repository.related(scope, page: 3),
        throwsArgumentError,
      );
    },
  );

  test(
    'offline read reduces personal unread only, not total or opposite-side rank',
    () async {
      final store = _store();
      await store.mergeRecords('properties', <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 91,
          'match_summary': <String, dynamic>{'count': 20, 'unread_count': 20},
        },
        <String, dynamic>{'id': 92},
      ]);
      await store.mergeRecords('customers', <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 27,
          'match_summary': <String, dynamic>{'count': 20, 'unread_count': 20},
        },
      ]);
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      var refreshes = 0;
      final repository = MatchNotificationRepository(
        client,
        store,
        () => refreshes++,
      );
      await repository.related(const RelatedMatchScope.property(91));
      adapter.offline = true;
      await repository.markRead(MatchNotification.fromJson(_notification(1)));
      final properties = await store.records('properties');
      final summary = MatchSummary.fromJson(
        properties.firstWhere((item) => item['id'] == 91)['match_summary'],
      );
      expect(summary?.count, 20);
      expect(summary?.unreadCount, 19);
      expect(
        properties.firstWhere((item) => item['id'] == 92)['match_summary'],
        isNull,
      );
      final customer = MatchSummary.fromJson(
        (await store.records('customers')).single['match_summary'],
      );
      expect(
        customer?.unreadCount,
        20,
      ); // The pair is outside this customer's top20.
      final page = await repository.related(
        const RelatedMatchScope.property(91),
      );
      expect(page.total, 20);
      expect(page.unreadCount, 19);
      expect(page.items.first.isRead, isTrue);
      await repository.markRead(MatchNotification.fromJson(_notification(1)));
      expect(await store.pendingCount(), 1);
      expect(
        (await repository.related(
          const RelatedMatchScope.property(91),
        )).unreadCount,
        19,
      );
      expect(refreshes, 2);
      store.configure(_user(8, 3));
      expect(
        (await repository.related(const RelatedMatchScope.property(91))).total,
        isNull,
      );
      expect(await store.pendingCount(), 0);
      store.configure(_user(7, 4));
      expect(
        (await repository.related(const RelatedMatchScope.property(91))).total,
        isNull,
      );
      store.configure(_user(7, 3));
      expect(
        (await repository.related(
          const RelatedMatchScope.property(91),
        )).unreadCount,
        19,
      );
    },
  );

  test(
    'automatic refresh retains expanded20 using only freshly ranked pages',
    () async {
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      final repository = MatchNotificationRepository(client, _store());
      final container = ProviderContainer(
        overrides: [
          matchNotificationRepositoryProvider.overrideWithValue(repository),
          matchSessionProvider.overrideWithValue('session'),
          matchIdentityProvider.overrideWithValue('identity'),
        ],
      );
      addTearDown(container.dispose);
      const scope = RelatedMatchScope.property(91);
      final subscription = container.listen(
        relatedMatchesProvider(scope),
        (_, _) {},
      );
      addTearDown(subscription.close);
      await container.read(relatedMatchesProvider(scope).future);
      await container.read(relatedMatchesProvider(scope).notifier).loadMore();
      adapter.offset = 100;
      container.read(matchDataRevisionProvider.notifier).refresh();
      final refreshed = await container.read(
        relatedMatchesProvider(scope).future,
      );
      expect(
        refreshed.items.map((item) => item.id),
        List<int>.generate(20, (i) => i + 101),
      );
      expect(refreshed.currentPage, 2);
      expect(refreshed.total, 20);
      expect(
        adapter.requests.map((request) => request.queryParameters['page']),
        <int>[1, 2, 1, 2],
      );
      adapter.offline = true;
      await container.read(relatedMatchesProvider(scope).notifier).refresh();
      final offline = container
          .read(relatedMatchesProvider(scope))
          .requireValue;
      expect(offline.items.length, 20);
      expect(offline.items.any((item) => item.id <= 20), isFalse);
    },
  );

  test(
    'online read sends displayed version and leaves newer unread server version untouched',
    () async {
      final store = _store();
      await store.mergeRecords('properties', <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 91,
          'match_summary': <String, dynamic>{'count': 20, 'unread_count': 20},
        },
      ]);
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      var refreshes = 0;
      final repository = MatchNotificationRepository(
        client,
        store,
        () => refreshes++,
      );
      final first = await repository.related(
        const RelatedMatchScope.property(91),
      );
      adapter.version = 2;
      final result = await repository.markRead(first.items.first);
      expect(adapter.requests.last.data, <String, dynamic>{
        'notification_version': 1,
      });
      expect(result.version, 2);
      expect(result.isRead, isFalse);
      expect(refreshes, 1);
      expect(
        MatchSummary.fromJson(
          (await store.records('properties')).single['match_summary'],
        )?.unreadCount,
        20,
      );
      final cached = MatchNotification.fromJson(
        (await store.records(
          'match-notifications',
        )).firstWhere((item) => item['id'] == 1),
      );
      expect(cached.version, 2);
      expect(cached.isRead, isFalse);
      adapter.offline = true;
      final page = await repository.related(
        const RelatedMatchScope.property(91),
      );
      expect(page.unreadCount, 20);
      expect(page.items.first.isRead, isFalse);
    },
  );

  test(
    'stale offline detail cannot read a cached higher notification version',
    () async {
      final store = _store();
      final adapter = _Adapter()..version = 2;
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      final repository = MatchNotificationRepository(client, store);
      await repository.related(const RelatedMatchScope.property(91));
      adapter.offline = true;
      await repository.markRead(MatchNotification.fromJson(_notification(1)));
      final page = await repository.related(
        const RelatedMatchScope.property(91),
      );
      expect(page.items.first.version, 2);
      expect(page.items.first.isRead, isFalse);
      expect(page.unreadCount, 20);
      final cached = MatchNotification.fromJson(
        (await store.records(
          'match-notifications',
        )).firstWhere((item) => item['id'] == 1),
      );
      expect(cached.version, 2);
      expect(cached.isRead, isFalse);
      expect((await store.operations()).single['body'], <String, dynamic>{
        'notification_version': 1,
      });
    },
  );

  test('late response cannot populate another tenant/user cache', () async {
    final store = _store();
    final adapter = _Adapter()..gate = Completer<void>();
    final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
    addTearDown(client.close);
    final repository = MatchNotificationRepository(client, store);
    final pending = repository.related(const RelatedMatchScope.property(91));
    await adapter.started.future;
    store.configure(_user(8, 4));
    adapter.gate!.complete();
    await pending;
    expect(await store.records('related-matches'), isEmpty);
    store.configure(_user(7, 3));
    expect(await store.records('related-matches'), isEmpty);
    await store.clearScope();
    store.configure(_user(7, 3));
    expect(await store.records('related-matches'), isEmpty);
  });

  test(
    'offline status change retires old membership and makes summaries unknown',
    () async {
      final store = _store();
      await store.mergeRecords('properties', <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 91,
          'status': 'available',
          'match_summary': <String, dynamic>{'count': 20, 'unread_count': 20},
        },
      ]);
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      final matches = MatchNotificationRepository(client, store);
      await matches.related(const RelatedMatchScope.property(91));
      adapter.offline = true;
      await PropertyRepository(client, store).changeStatus(
        91,
        status: 'archived',
        expectedUpdatedAt: '',
        expectedVersion: 1,
      );
      expect(
        MatchSummary.fromJson(
          (await store.records('properties')).single['match_summary'],
        ),
        isNull,
      );
      expect(
        (await matches.related(const RelatedMatchScope.property(91))).total,
        isNull,
      );
    },
  );

  test('access denial must not fall back to a cached related list', () async {
    final adapter = _Adapter();
    final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
    addTearDown(client.close);
    final repository = MatchNotificationRepository(client, _store());
    await repository.related(const RelatedMatchScope.property(91));
    adapter.denied = true;
    await expectLater(
      repository.related(const RelatedMatchScope.property(91)),
      throwsA(isA<DioException>()),
    );
  });

  testWidgets('unknown badge is not zero; reading keeps its total visible', (
    tester,
  ) async {
    Future<void> render(MatchSummary? summary) => tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: RelatedMatchBadge(
            scope: const RelatedMatchScope.property(91),
            summary: summary,
          ),
        ),
      ),
    );
    await render(null);
    expect(find.text('تطبیق‌ها · نامشخص'), findsOneWidget);
    expect(find.textContaining('۰ تطبیق'), findsNothing);
    await render(const MatchSummary(count: 20, unreadCount: 3));
    expect(find.text('۲۰ تطبیق · ۳ جدید'), findsOneWidget);
    await render(const MatchSummary(count: 20, unreadCount: 2));
    expect(find.text('۲۰ تطبیق · ۲ جدید'), findsOneWidget);
    expect(
      tester.getSize(find.byType(FilledButton)).height,
      greaterThanOrEqualTo(48),
    );
  });

  for (final display in PropertyCardDisplay.values) {
    testWidgets(
      '${display.name} and dashboard-map cards show accessible badges at native sizes',
      (tester) async {
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        tester.view.devicePixelRatio = 1;
        for (final size in <Size>[const Size(375, 812), const Size(812, 375)]) {
          tester.view.physicalSize = size;
          await tester.pumpWidget(
            MaterialApp(
              theme: ThemeData(
                colorScheme: ColorScheme.fromSeed(
                  seedColor: Colors.teal,
                  brightness: Brightness.dark,
                ),
              ),
              builder: (context, child) => MediaQuery(
                data: MediaQuery.of(context).copyWith(
                  textScaler: const TextScaler.linear(2),
                  disableAnimations: true,
                ),
                child: child!,
              ),
              home: Scaffold(
                body: ListView(
                  children: <Widget>[
                    PropertyCard.fromMap(<String, dynamic>{
                      'id': 91,
                      'title': 'خانه نمونه',
                      'match_summary': <String, dynamic>{
                        'count': 20,
                        'unread_count': 3,
                      },
                    }, display: display),
                  ],
                ),
              ),
            ),
          );
          await tester.pumpAndSettle();
          expect(find.text('۲۰ تطبیق · ۳ جدید'), findsOneWidget);
          expect(tester.takeException(), isNull);
        }
      },
    );
  }

  testWidgets(
    'related list opens without reading, detail reads only on exit and refreshes list',
    (tester) async {
      final store = _store();
      final adapter = _Adapter();
      final client = ApiClient(dio: Dio()..httpClientAdapter = adapter);
      addTearDown(client.close);
      late ProviderContainer container;
      final repository = MatchNotificationRepository(
        client,
        store,
        () => container.read(matchDataRevisionProvider.notifier).refresh(),
      );
      container = ProviderContainer(
        overrides: [
          offlineStoreProvider.overrideWithValue(store),
          matchNotificationRepositoryProvider.overrideWithValue(repository),
          authControllerProvider.overrideWithBuild(
            (ref, notifier) =>
                AuthState(status: AuthStatus.signedIn, user: _user(7, 3)),
          ),
        ],
      );
      final router = GoRouter(
        initialLocation: '/properties/91/matches',
        routes: <RouteBase>[
          GoRoute(
            path: '/properties/91/matches',
            builder: (_, _) =>
                const RelatedMatchesPage(scope: RelatedMatchScope.property(91)),
          ),
          GoRoute(
            path: '/match-notifications/:id',
            builder: (_, state) => MatchNotificationDetailPage(
              notificationId: int.parse(state.pathParameters['id']!),
            ),
          ),
        ],
      );
      addTearDown(router.dispose);
      addTearDown(container.dispose);
      await tester.pumpWidget(
        UncontrolledProviderScope(
          container: container,
          child: MaterialApp.router(routerConfig: router),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        adapter.requests.where((request) => request.method == 'POST'),
        isEmpty,
      );
      await tester.tap(find.text('مشتری 1'));
      await tester.pumpAndSettle();
      expect(find.text('جزئیات اعلان'), findsOneWidget);
      expect(
        adapter.requests.where((request) => request.method == 'POST'),
        isEmpty,
      );
      router.pop();
      await tester.pumpAndSettle();
      await tester.runAsync(
        () => Future<void>.delayed(const Duration(milliseconds: 20)),
      );
      await tester.pumpAndSettle();
      expect(
        adapter.requests.where((request) => request.method == 'POST'),
        hasLength(1),
      );
      expect(
        adapter.requests.firstWhere((request) => request.method == 'POST').data,
        <String, dynamic>{'notification_version': 1},
      );
      expect(find.text('۲۰ تطبیق مرتبط · ۱۹ خوانده نشده'), findsOneWidget);
      expect(find.text('خوانده شده'), findsOneWidget);
      await tester.pumpWidget(const SizedBox.shrink());
    },
  );
}

OfflineStore _store() =>
    OfflineStore(const FlutterSecureStorage())..configure(_user(7, 3));
Map<String, dynamic> _user(int id, int agency) => <String, dynamic>{
  'id': id,
  'role': 'agent',
  'agency': <String, dynamic>{'id': agency},
  'permissions': <String>['properties.view', 'customers.view'],
};
Map<String, dynamic> _notification(
  int id, {
  bool read = false,
  int version = 1,
}) => <String, dynamic>{
  'id': id,
  'version': version,
  'title': 'تطبیق جدید',
  'body': 'دلیل تطبیق',
  'short_reason': 'متراژ و بودجه سازگار',
  'is_read': read,
  'match': <String, dynamic>{
    'id': id,
    'property_rank': id <= 20 ? id : null,
    'customer_rank': null,
    'score': 95,
    'match_mode': 'converted',
    'property': <String, dynamic>{'id': 91, 'title': 'خانه نمونه'},
    'customer': <String, dynamic>{'id': 27, 'full_name': 'مشتری $id'},
  },
};

final class _Adapter implements HttpClientAdapter {
  bool offline = false;
  bool denied = false;
  int offset = 0;
  int version = 1;
  final requests = <RequestOptions>[];
  final readIds = <int>{};
  final started = Completer<void>();
  Completer<void>? gate;

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);
    if (!started.isCompleted) started.complete();
    if (gate != null) await gate!.future;
    if (offline) {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
        error: const SocketException('offline'),
      );
    }
    if (denied) {
      return ResponseBody.fromString(
        jsonEncode(<String, dynamic>{
          'error': <String, dynamic>{'code': 'FORBIDDEN'},
        }),
        403,
        headers: <String, List<String>>{
          'content-type': <String>['application/json'],
        },
      );
    }
    Map<String, dynamic> envelope;
    if (options.path.endsWith('/matches')) {
      final page = options.queryParameters['page'] as int;
      envelope = <String, dynamic>{
        'data': List<Map<String, dynamic>>.generate(10, (index) {
          final id = offset + (page - 1) * 10 + index + 1;
          return _notification(
            id,
            read: readIds.contains(id),
            version: version,
          );
        }),
        'meta': <String, dynamic>{
          'current_page': page,
          'last_page': 2,
          'per_page': 10,
          'total': 20,
          'unread_count': 20 - readIds.length,
        },
      };
    } else if (options.path.startsWith('/match-notifications/')) {
      final id = int.parse(options.path.split('/')[2]);
      if (options.method == 'POST' &&
          (options.data as Map?)?['notification_version'] == version) {
        readIds.add(id);
      }
      envelope = <String, dynamic>{
        'data': _notification(id, read: readIds.contains(id), version: version),
      };
    } else {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
        error: const SocketException('offline'),
      );
    }
    return ResponseBody.fromString(
      jsonEncode(envelope),
      200,
      headers: <String, List<String>>{
        'content-type': <String>['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
