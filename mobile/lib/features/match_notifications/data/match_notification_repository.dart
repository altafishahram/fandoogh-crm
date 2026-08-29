import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final matchNotificationRepositoryProvider =
    Provider<MatchNotificationRepository>((ref) {
      return MatchNotificationRepository(
        ref.watch(apiClientProvider),
        ref.watch(offlineStoreProvider),
      );
    });

final matchNotificationsProvider =
    FutureProvider.autoDispose<MatchNotificationPage>((ref) {
      return ref.watch(matchNotificationRepositoryProvider).list();
    });

final matchNotificationProvider = FutureProvider.autoDispose
    .family<MatchNotification, int>((ref, id) {
      return ref.watch(matchNotificationRepositoryProvider).find(id);
    });

final matchNotificationUnreadCountProvider = FutureProvider<int>((ref) {
  return ref.watch(matchNotificationRepositoryProvider).unreadCount();
});

/// مخزن اعلان‌های تطبیق.
///
/// اعلان‌ها فقط در محدوده کاربر فعلی cache می‌شوند. در حالت آفلاین، فهرست و
/// جزئیات از همین cache خوانده می‌شوند و خواندن اعلان به‌صورت عملیات محلی
/// در صف همگام‌سازی قرار می‌گیرد.
class MatchNotificationRepository extends ApiRepository {
  MatchNotificationRepository(super.client, [this.offlineStore]);

  static const _resource = 'match-notifications';
  final OfflineStore? offlineStore;

  Future<MatchNotificationPage> list({
    int page = 1,
    bool unreadOnly = false,
    int perPage = 50,
  }) async {
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        '/match-notifications',
        queryParameters: ApiRepository.compact(<String, Object?>{
          'page': page,
          'per_page': perPage,
          if (unreadOnly) 'unread_only': true,
        }),
      );
      final result = MatchNotificationPage.fromEnvelope(
        response.data ?? <String, dynamic>{},
      );
      await offlineStore?.mergeRecords(
        _resource,
        result.items.map((item) => item.toJson()),
      );
      final unread =
          result.unreadCount ??
          result.items.where((item) => !item.isRead).length;
      return result.copyWith(unreadCount: unread);
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || offlineStore?.isConfigured != true) {
        rethrow;
      }
      return _cachedPage(unreadOnly: unreadOnly);
    }
  }

  Future<int> unreadCount() async {
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        '/match-notifications',
        queryParameters: const <String, Object?>{
          'page': 1,
          'per_page': 1,
          'unread_only': true,
        },
      );
      final result = MatchNotificationPage.fromEnvelope(
        response.data ?? <String, dynamic>{},
      );
      await offlineStore?.mergeRecords(
        _resource,
        result.items.map((item) => item.toJson()),
      );
      return result.unreadCount ??
          result.items.where((item) => !item.isRead).length;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || offlineStore?.isConfigured != true) {
        rethrow;
      }
      final records = await offlineStore!.records(_resource);
      return records
          .map(MatchNotification.fromJson)
          .where((item) => !item.isRead)
          .length;
    }
  }

  Future<MatchNotification> find(int id) async {
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        '/match-notifications/$id',
      );
      final item = MatchNotification.fromJson(
        ApiRepository.unwrapData(response.data),
      );
      await offlineStore?.mergeRecords(_resource, <Map<String, dynamic>>[
        item.toJson(),
      ]);
      return item;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || offlineStore?.isConfigured != true) {
        rethrow;
      }
      final records = await offlineStore!.records(_resource);
      for (final record in records) {
        final item = MatchNotification.fromJson(record);
        if (item.id == id) return item;
      }
      rethrow;
    }
  }

  Future<MatchNotification> markRead(MatchNotification notification) async {
    final path = '/match-notifications/${notification.id}/read';
    try {
      final response = await client.dio.post<Map<String, dynamic>>(path);
      final payload = ApiRepository.unwrapData(response.data);
      final serverItem = payload.isEmpty
          ? null
          : MatchNotification.fromJson(payload);
      final updated =
          serverItem ??
          notification.copyWith(
            isRead: true,
            readAt: DateTime.now().toUtc(),
            updatedAt: DateTime.now().toUtc(),
          );
      await offlineStore?.mergeRecords(_resource, <Map<String, dynamic>>[
        updated.toJson(),
      ]);
      return updated;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || offlineStore?.isConfigured != true) {
        rethrow;
      }

      final updated = notification.copyWith(
        isRead: true,
        readAt: DateTime.now().toUtc(),
        updatedAt: DateTime.now().toUtc(),
      );
      final store = offlineStore!;
      await store.mergeRecords(_resource, <Map<String, dynamic>>[
        updated.toJson(),
      ]);
      final operations = await store.operations();
      final alreadyQueued = operations.any(
        (operation) =>
            operation['path'] == path && operation['status'] != 'synced',
      );
      if (!alreadyQueued) {
        await store.enqueue(
          resource: _resource,
          action: 'read',
          path: path,
          body: <String, Object?>{
            if (notification.version != null)
              'notification_version': notification.version,
          },
          recordId: notification.id,
          baseVersion: notification.version,
          operationKey: 'match-notification-read-${notification.id}',
        );
      }
      return updated;
    }
  }

  Future<MatchNotificationPage> _cachedPage({required bool unreadOnly}) async {
    final records = await offlineStore!.records(_resource);
    final items =
        records
            .map(MatchNotification.fromJson)
            .where((item) => !unreadOnly || !item.isRead)
            .toList(growable: false)
          ..sort(_newestFirst);
    final allItems = records.map(MatchNotification.fromJson);
    return MatchNotificationPage(
      items: items,
      currentPage: 1,
      lastPage: 1,
      unreadCount: allItems.where((item) => !item.isRead).length,
      fromCache: true,
    );
  }

  static int _newestFirst(MatchNotification a, MatchNotification b) {
    final aDate = a.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    final bDate = b.createdAt ?? DateTime.fromMillisecondsSinceEpoch(0);
    return bDate.compareTo(aDate);
  }

  static bool _isNetwork(ApiFailure failure) =>
      failure.code == 'NETWORK_UNAVAILABLE' ||
      failure.code == 'NETWORK_TIMEOUT';
}
