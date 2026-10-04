import 'dart:io';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/match_notifications_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() => FlutterSecureStorage.setMockInitialValues(<String, String>{}));

  test('parses a nested match notification and Persian labels', () {
    final notification = MatchNotification.fromJson(<String, dynamic>{
      'id': 12,
      'title': 'تطبیق جدید ملک و مشتری',
      'body': 'یک ملک با نیاز مشتری همخوان است.',
      'is_read': false,
      'created_at': '2026-08-26T10:00:00Z',
      'match': <String, dynamic>{
        'id': 44,
        'score': 87.5,
        'match_mode': 'converted',
        'property_id': 91,
        'customer_id': 27,
        'matched_deposit_min': 50000000,
        'matched_deposit_max': 75000000,
        'property': <String, dynamic>{'title': 'آپارتمان نمونه'},
        'customer': <String, dynamic>{'full_name': 'مشتری نمونه'},
      },
    });

    expect(notification.id, 12);
    expect(notification.matchId, 44);
    expect(notification.propertyTitle, 'آپارتمان نمونه');
    expect(notification.customerName, 'مشتری نمونه');
    expect(notification.scoreLabel, '۸۷.۵ از ۱۰۰');
    expect(notification.modeLabel, 'تطبیق با تبدیل ودیعه و اجاره');
    expect(notification.dateLabel, '۴ شهریور ۱۴۰۵');
  });

  test('uses notification cache and queues read while offline', () async {
    const storage = FlutterSecureStorage();
    final store = OfflineStore(storage)
      ..configure(<String, dynamic>{
        'id': 7,
        'agency': <String, dynamic>{'id': 3},
      });
    final cached = <String, dynamic>{
      'id': 12,
      'title': 'تطبیق آفلاین',
      'body': 'اعلان ذخیره‌شده',
      'is_read': false,
      'match_id': 44,
      'created_at': '2026-08-26T10:00:00Z',
    };
    await store.mergeRecords('match-notifications', <Map<String, dynamic>>[
      cached,
    ]);

    final dio = Dio()..httpClientAdapter = _OfflineAdapter();
    final client = ApiClient(dio: dio);
    final repository = MatchNotificationRepository(client, store);

    final page = await repository.list();
    expect(page.fromCache, isTrue);
    expect(page.items.single.title, 'تطبیق آفلاین');
    expect(page.unreadCount, 1);

    final updated = await repository.markRead(page.items.single);
    expect(updated.isRead, isTrue);
    expect(
      (await store.records('match-notifications')).single['is_read'],
      isTrue,
    );
    expect(await store.pendingCount(), 1);
    expect(
      (await store.operations()).single['path'],
      '/match-notifications/12/read',
    );
    client.close();
  });

  test('unread provider returns the repository count', () async {
    final repository = _FakeMatchNotificationRepository(unreadValue: 3);
    final container = ProviderContainer(
      overrides: [
        matchNotificationRepositoryProvider.overrideWithValue(repository),
      ],
    );
    addTearDown(container.dispose);

    expect(
      await container.read(matchNotificationUnreadCountProvider.future),
      3,
    );
  });

  testWidgets('notification list renders unread state and offline banner', (
    tester,
  ) async {
    final item = MatchNotification.fromJson(<String, dynamic>{
      'id': 12,
      'title': 'تطبیق جدید',
      'body': 'ملک و مشتری همخوان هستند.',
      'is_read': false,
      'score': 91,
      'match_mode': 'direct',
      'property': <String, dynamic>{'title': 'خانه نمونه'},
      'customer': <String, dynamic>{'full_name': 'علی رضایی'},
      'created_at': '2026-08-26T10:00:00Z',
    });
    final repository = _FakeMatchNotificationRepository(
      page: MatchNotificationPage(
        items: <MatchNotification>[item],
        currentPage: 1,
        lastPage: 1,
        unreadCount: 1,
        fromCache: true,
      ),
      unreadValue: 1,
    );

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          matchNotificationRepositoryProvider.overrideWithValue(repository),
        ],
        child: const MaterialApp(home: MatchNotificationsPage()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('اعلان‌های تطبیق'), findsOneWidget);
    expect(find.text('تطبیق جدید'), findsOneWidget);
    expect(find.text('خانه نمونه • علی رضایی'), findsOneWidget);
    expect(find.byIcon(Icons.cloud_off_rounded), findsOneWidget);
    expect(find.text('۹۱ از ۱۰۰'), findsOneWidget);
  });
}

final class _FakeMatchNotificationRepository
    extends MatchNotificationRepository {
  _FakeMatchNotificationRepository({this.page, this.unreadValue = 0})
    : super(ApiClient());

  final MatchNotificationPage? page;
  final int unreadValue;

  @override
  Future<MatchNotificationPage> list({
    int page = 1,
    bool unreadOnly = false,
    int perPage = 50,
  }) async =>
      this.page ??
      const MatchNotificationPage(
        items: <MatchNotification>[],
        currentPage: 1,
        lastPage: 1,
      );

  @override
  Future<int> unreadCount() async => unreadValue;
}

final class _OfflineAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    throw DioException(
      requestOptions: options,
      type: DioExceptionType.connectionError,
      error: const SocketException('offline'),
    );
  }

  @override
  void close({bool force = false}) {}
}
