import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final matchNotificationRepositoryProvider =
    Provider<MatchNotificationRepository>((ref) {
      return MatchNotificationRepository(
        ref.watch(apiClientProvider),
        ref.watch(offlineStoreProvider),
        () {
          if (ref.mounted) {
            ref.read(matchDataRevisionProvider.notifier).refresh();
          }
        },
      );
    });

final matchNotificationsProvider =
    FutureProvider.autoDispose<MatchNotificationPage>((ref) {
      ref.watch(matchSessionProvider);
      ref.watch(matchDataRevisionProvider);
      return ref.watch(matchNotificationRepositoryProvider).list();
    });

final matchNotificationProvider = FutureProvider.autoDispose
    .family<MatchNotification, int>((ref, id) {
      ref.watch(matchSessionProvider);
      return ref.watch(matchNotificationRepositoryProvider).find(id);
    });

final matchNotificationUnreadCountProvider = FutureProvider<int>((ref) {
  ref.watch(matchSessionProvider);
  ref.watch(matchDataRevisionProvider);
  return ref.watch(matchNotificationRepositoryProvider).unreadCount();
});

/// مخزن اعلان‌های تطبیق.
///
/// اعلان‌ها فقط در محدوده کاربر فعلی cache می‌شوند. در حالت آفلاین، فهرست و
/// جزئیات از همین cache خوانده می‌شوند و خواندن اعلان به‌صورت عملیات محلی
/// در صف همگام‌سازی قرار می‌گیرد.
class MatchNotificationRepository extends ApiRepository {
  MatchNotificationRepository(
    super.client, [
    this.offlineStore,
    this.onChanged,
  ]);

  static const _resource = 'match-notifications';
  final OfflineStore? offlineStore;
  final void Function()? onChanged;

  /// Only cache exact membership returned by the chosen side's endpoint.
  /// Never infer it from the union in the global notification cache.
  Future<MatchNotificationPage> related(
    RelatedMatchScope scope, {
    int page = 1,
  }) async {
    if (page < 1 || page > 2) throw ArgumentError.value(page, 'page');
    final store = offlineStore?.scoped();
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        scope.path,
        queryParameters: <String, Object?>{'page': page, 'per_page': 10},
      );
      var result = MatchNotificationPage.fromEnvelope(
        response.data ?? <String, dynamic>{},
      );
      result = result.copyWith(
        items: result.items.take(10).toList(growable: false),
        currentPage: page,
        lastPage: result.lastPage.clamp(1, 2),
        total: result.total?.clamp(0, 20),
        perPage: 10,
      );
      if (store?.isConfigured == true &&
          store?.scopeKey == offlineStore?.scopeKey) {
        await store!.updateRecords('related-matches', (cached) {
          cached.removeWhere(
            (entry) =>
                entry['id'] == '${scope.cacheKey}:$page' ||
                (page == 1 && entry['scope'] == scope.cacheKey),
          );
          cached.add(<String, dynamic>{
            'id': '${scope.cacheKey}:$page',
            'scope': scope.cacheKey,
            'page': page,
            'data': result.items.map((item) => item.toJson()).toList(),
            'meta': <String, dynamic>{
              'current_page': page,
              'last_page': result.lastPage,
              'per_page': 10,
              'total': result.total,
              'unread_count': result.unreadCount,
            },
          });
        });
        await store.mergeRecords(
          _resource,
          result.items.map((item) => item.toJson()),
        );
        if (result.total != null && result.unreadCount != null) {
          final records = await store.records(scope.resource);
          final record = records
              .where((item) => item['id'] == scope.id)
              .firstOrNull;
          if (record != null) {
            await store.mergeRecords(scope.resource, <Map<String, dynamic>>[
              <String, dynamic>{
                ...record,
                'match_summary': <String, dynamic>{
                  'count': result.total,
                  'unread_count': result.unreadCount,
                },
              },
            ]);
          }
        }
      }
      return result;
    } catch (error) {
      if (!_isNetwork(apiFailureFrom(error)) || store?.isConfigured != true) {
        rethrow;
      }
      final cached = await store!.records('related-matches');
      final entry = cached
          .where((item) => item['id'] == '${scope.cacheKey}:$page')
          .firstOrNull;
      if (entry == null) {
        return MatchNotificationPage(
          items: const <MatchNotification>[],
          currentPage: page,
          lastPage: page,
          perPage: 10,
          fromCache: true,
        );
      }
      return MatchNotificationPage.fromEnvelope(entry, fromCache: true);
    }
  }

  Future<MatchNotificationPage> list({
    int page = 1,
    bool unreadOnly = false,
    int perPage = 50,
  }) async {
    final store = offlineStore?.scoped();
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
      await store?.mergeRecords(
        _resource,
        result.items.map((item) => item.toJson()),
      );
      final unread =
          result.unreadCount ??
          result.items.where((item) => !item.isRead).length;
      return result.copyWith(unreadCount: unread);
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || store?.isConfigured != true) {
        rethrow;
      }
      return _cachedPage(store!, unreadOnly: unreadOnly);
    }
  }

  Future<int> unreadCount() async {
    final store = offlineStore?.scoped();
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
      await store?.mergeRecords(
        _resource,
        result.items.map((item) => item.toJson()),
      );
      return result.unreadCount ??
          result.items.where((item) => !item.isRead).length;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || store?.isConfigured != true) {
        rethrow;
      }
      final records = await store!.records(_resource);
      return records
          .map(MatchNotification.fromJson)
          .where((item) => !item.isRead)
          .length;
    }
  }

  Future<MatchNotification> find(int id) async {
    final store = offlineStore?.scoped();
    try {
      final response = await client.dio.get<Map<String, dynamic>>(
        '/match-notifications/$id',
      );
      final item = MatchNotification.fromJson(
        ApiRepository.unwrapData(response.data),
      );
      await store?.mergeRecords(_resource, <Map<String, dynamic>>[
        item.toJson(),
      ]);
      return item;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || store?.isConfigured != true) {
        rethrow;
      }
      final records = await store!.records(_resource);
      for (final record in records) {
        final item = MatchNotification.fromJson(record);
        if (item.id == id) return item;
      }
      rethrow;
    }
  }

  Future<MatchNotification> markRead(MatchNotification notification) async {
    final store = offlineStore?.scoped();
    if (notification.isRead) return notification;
    final cached = store?.isConfigured == true
        ? (await store!.records(
            _resource,
          )).where((item) => item['id'] == notification.id).firstOrNull
        : null;
    final displayedVersion = notification.version ?? 1;
    final wasRead =
        cached != null &&
        MatchNotification.fromJson(cached).isRead &&
        (MatchNotification.fromJson(cached).version ?? 1) >= displayedVersion;
    final cachedNewer =
        cached != null &&
        (MatchNotification.fromJson(cached).version ?? 1) > displayedVersion;
    if (store?.scopeKey != offlineStore?.scopeKey) return notification;
    final path = '/match-notifications/${notification.id}/read';
    try {
      final response = await client.dio.post<Map<String, dynamic>>(
        path,
        data: <String, Object?>{
          if (notification.version != null)
            'notification_version': notification.version,
        },
      );
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
      if (store?.isConfigured == true) {
        await _cacheNotifications(store!, <MatchNotification>[updated]);
        if (updated.isRead &&
            !wasRead &&
            !cachedNewer &&
            (updated.version ?? displayedVersion) <= displayedVersion) {
          await _updateReadCaches(store, notification);
        }
      }
      if (store?.scopeKey == offlineStore?.scopeKey) onChanged?.call();
      return updated;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (!_isNetwork(failure) || store?.isConfigured != true) {
        rethrow;
      }

      final updated = notification.copyWith(
        isRead: true,
        readAt: DateTime.now().toUtc(),
        updatedAt: DateTime.now().toUtc(),
      );
      await _cacheNotifications(store!, <MatchNotification>[updated]);
      final operations = await store.operations();
      final queued = operations
          .where(
            (operation) =>
                operation['path'] == path && operation['status'] != 'synced',
          )
          .firstOrNull;
      if (queued == null) {
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
          operationKey:
              'match-notification-read-${notification.id}-v$displayedVersion',
        );
      } else if ((int.tryParse('${queued['base_version']}') ?? 1) <
          displayedVersion) {
        await store.replaceOperation(<String, dynamic>{
          ...queued,
          'status': 'pending',
          'base_version': displayedVersion,
          'body': <String, Object?>{'notification_version': displayedVersion},
          'operation_key':
              'match-notification-read-${notification.id}-v$displayedVersion',
        });
      }
      if (!wasRead && !cachedNewer) {
        await _updateReadCaches(store, notification);
      }
      if (store.scopeKey == offlineStore?.scopeKey) onChanged?.call();
      return updated;
    }
  }

  Future<MatchNotificationPage> _cachedPage(
    OfflineStore store, {
    required bool unreadOnly,
  }) async {
    final records = await store.records(_resource);
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

  Future<void> _updateReadCaches(
    OfflineStore store,
    MatchNotification item,
  ) async {
    final pages = await store.records('related-matches');
    final memberships = <String>{};
    final newerScopes = <String>{};
    final version = item.version ?? 1;
    for (final page in pages) {
      final data = page['data'];
      if (data is! List) continue;
      for (final entry in data.whereType<Map>().where(
        (entry) => entry['id'] == item.id,
      )) {
        final cached = MatchNotification.fromJson(
          Map<String, dynamic>.from(entry),
        );
        if ((cached.version ?? 1) > version) {
          newerScopes.add('${page['scope']}');
        } else if (!cached.isRead) {
          memberships.add('${page['scope']}');
        }
      }
    }
    for (final page in pages) {
      final data = page['data'];
      if (data is List) {
        page['data'] = data
            .map(
              (entry) =>
                  entry is Map &&
                      entry['id'] == item.id &&
                      (int.tryParse('${entry['version']}') ?? 1) <= version
                  ? <String, dynamic>{
                      ...Map<String, dynamic>.from(entry),
                      'is_read': true,
                      'read_at': DateTime.now().toUtc().toIso8601String(),
                    }
                  : entry,
            )
            .toList();
      }
      if (memberships.contains('${page['scope']}') && page['meta'] is Map) {
        final meta = Map<String, dynamic>.from(page['meta'] as Map);
        final unread = int.tryParse('${meta['unread_count']}');
        if (unread != null) meta['unread_count'] = (unread - 1).clamp(0, 20);
        page['meta'] = meta;
      }
    }
    await store.replaceRecords('related-matches', pages);
    for (final (scope, rank) in <(RelatedMatchScope?, int?)>[
      (
        item.propertyId == null
            ? null
            : RelatedMatchScope.property(item.propertyId!),
        item.propertyRank,
      ),
      (
        item.customerId == null
            ? null
            : RelatedMatchScope.customer(item.customerId!),
        item.customerRank,
      ),
    ]) {
      if (scope == null ||
          newerScopes.contains(scope.cacheKey) ||
          !((rank != null && rank >= 1 && rank <= 20) ||
              memberships.contains(scope.cacheKey))) {
        continue;
      }
      final records = await store.records(scope.resource);
      for (final record in records.where(
        (record) => record['id'] == scope.id,
      )) {
        final summary = MatchSummary.fromJson(record['match_summary']);
        if (summary != null) {
          record['match_summary'] = summary.readOne().toJson();
        }
      }
      await store.replaceRecords(scope.resource, records);
      if (scope.side == RelatedMatchSide.property) {
        final dashboards = await store.records('dashboard');
        for (final dashboard in dashboards) {
          final recent = dashboard['recent_properties'];
          if (recent is! List) continue;
          for (final property in recent.whereType<Map>().where(
            (entry) => entry['id'] == scope.id,
          )) {
            final summary = MatchSummary.fromJson(property['match_summary']);
            if (summary != null) {
              property['match_summary'] = summary.readOne().toJson();
            }
          }
        }
        await store.replaceRecords('dashboard', dashboards);
      }
    }
  }

  Future<void> _cacheNotifications(
    OfflineStore store,
    Iterable<MatchNotification> incoming,
  ) async {
    final cached = <int, MatchNotification>{
      for (final json in await store.records(_resource))
        MatchNotification.fromJson(json).id: MatchNotification.fromJson(json),
    };
    final accepted = incoming.where(
      (item) => (item.version ?? 1) >= (cached[item.id]?.version ?? 1),
    );
    await store.mergeRecords(_resource, accepted.map((item) => item.toJson()));
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
