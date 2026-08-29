import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class MatchNotificationsPage extends ConsumerWidget {
  const MatchNotificationsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notifications = ref.watch(matchNotificationsProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('اعلان‌های تطبیق'),
        actions: <Widget>[
          IconButton(
            tooltip: 'تازه‌سازی اعلان‌ها',
            onPressed: () {
              ref.invalidate(matchNotificationsProvider);
              ref.invalidate(matchNotificationUnreadCountProvider);
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: notifications.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ErrorState(
            error: error,
            onRetry: () => ref.invalidate(matchNotificationsProvider),
          ),
          data: (page) => _NotificationList(
            page: page,
            onRefresh: () async {
              ref.invalidate(matchNotificationsProvider);
              await ref.read(matchNotificationsProvider.future);
              ref.invalidate(matchNotificationUnreadCountProvider);
            },
          ),
        ),
      ),
    );
  }
}

final class _NotificationList extends StatelessWidget {
  const _NotificationList({required this.page, required this.onRefresh});

  final MatchNotificationPage page;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return const EmptyState(
        icon: Icons.notifications_none_rounded,
        message: 'هنوز اعلان تطبیقی برای شما ثبت نشده است.',
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
        itemCount: page.items.length + (page.fromCache ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 10),
        itemBuilder: (context, index) {
          if (index == 0 && page.fromCache) {
            return _OfflineNotice(unreadCount: page.unreadCount);
          }
          final itemIndex = page.fromCache ? index - 1 : index;
          final item = page.items[itemIndex];
          return _NotificationCard(notification: item);
        },
      ),
    );
  }
}

final class _OfflineNotice extends StatelessWidget {
  const _OfflineNotice({required this.unreadCount});

  final int? unreadCount;

  @override
  Widget build(BuildContext context) {
    final count = unreadCount ?? 0;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.secondaryContainer,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: <Widget>[
          Icon(
            Icons.cloud_off_rounded,
            color: Theme.of(context).colorScheme.onSecondaryContainer,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              count == 0
                  ? 'اعلان‌های ذخیره‌شده در حالت آفلاین نمایش داده می‌شوند.'
                  : 'اعلان‌های ذخیره‌شده نمایش داده می‌شوند؛ ${persianDigits(count)} اعلان خوانده نشده است.',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Theme.of(context).colorScheme.onSecondaryContainer,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification});

  final MatchNotification notification;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final label = [
      notification.title,
      notification.propertyTitle,
      notification.customerName,
      notification.isRead ? 'خوانده شده' : 'خوانده نشده',
    ].join('، ');
    return Semantics(
      button: true,
      label: label,
      child: GlassPanel(
        padding: EdgeInsets.zero,
        borderRadius: BorderRadius.circular(20),
        child: InkWell(
          borderRadius: BorderRadius.circular(20),
          onTap: () => context.push('/match-notifications/${notification.id}'),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                _NotificationIcon(isRead: notification.isRead),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: <Widget>[
                          Expanded(
                            child: Text(
                              notification.title,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(
                                    fontWeight: notification.isRead
                                        ? FontWeight.w700
                                        : FontWeight.w900,
                                  ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          if (!notification.isRead)
                            Container(
                              width: 9,
                              height: 9,
                              margin: const EdgeInsets.only(top: 7),
                              decoration: BoxDecoration(
                                color: colors.primary,
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 7),
                      Text(
                        '${notification.propertyTitle} • ${notification.customerName}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: colors.onSurfaceVariant,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (notification.body.isNotEmpty) ...<Widget>[
                        const SizedBox(height: 5),
                        Text(
                          notification.body,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                      const SizedBox(height: 9),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: <Widget>[
                          _InfoChip(
                            icon: Icons.insights_rounded,
                            label: notification.scoreLabel,
                          ),
                          _InfoChip(
                            icon: Icons.swap_horiz_rounded,
                            label: notification.modeLabel,
                          ),
                          _InfoChip(
                            icon: Icons.event_outlined,
                            label: notification.dateLabel,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 4),
                Icon(Icons.chevron_left_rounded, color: colors.outline),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

final class _NotificationIcon extends StatelessWidget {
  const _NotificationIcon({required this.isRead});

  final bool isRead;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Container(
      width: 46,
      height: 46,
      decoration: BoxDecoration(
        color: isRead
            ? colors.surfaceContainerHighest
            : colors.primaryContainer,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Icon(
        isRead ? Icons.notifications_outlined : Icons.notifications_active,
        color: isRead ? colors.onSurfaceVariant : colors.onPrimaryContainer,
      ),
    );
  }
}

final class _InfoChip extends StatelessWidget {
  const _InfoChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(10),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 15),
          const SizedBox(width: 4),
          Text(label, style: Theme.of(context).textTheme.labelSmall),
        ],
      ),
    ),
  );
}
