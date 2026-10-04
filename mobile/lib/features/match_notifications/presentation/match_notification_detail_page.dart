import 'dart:async';

import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/auth/auth_state.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class MatchNotificationDetailPage extends ConsumerStatefulWidget {
  const MatchNotificationDetailPage({required this.notificationId, super.key});

  final int notificationId;

  @override
  ConsumerState<MatchNotificationDetailPage> createState() =>
      _MatchNotificationDetailPageState();
}

final class _MatchNotificationDetailPageState
    extends ConsumerState<MatchNotificationDetailPage> {
  MatchNotification? _viewedItem;
  String? _viewedIdentity;
  late ProviderContainer _container;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _container = ProviderScope.containerOf(context, listen: false);
  }

  @override
  void dispose() {
    final item = _viewedItem;
    // Reading is tied to leaving a successfully rendered detail, never to
    // opening a list. Do not send an old tenant's read after an access change.
    if (item != null &&
        !item.isRead &&
        _container.read(authControllerProvider).status == AuthStatus.signedIn &&
        _container.read(matchIdentityProvider) == _viewedIdentity) {
      final repository = _container.read(matchNotificationRepositoryProvider);
      unawaited(repository.markRead(item).catchError((Object _) => item));
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final notification = ref.watch(
      matchNotificationProvider(widget.notificationId),
    );
    return Scaffold(
      appBar: AppBar(title: const Text('جزئیات اعلان')),
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: notification.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ErrorState(
            error: error,
            onRetry: () => ref.invalidate(
              matchNotificationProvider(widget.notificationId),
            ),
          ),
          data: (item) {
            _viewedItem = item;
            _viewedIdentity = ref.read(matchIdentityProvider);
            return _NotificationDetailBody(notification: item);
          },
        ),
      ),
    );
  }
}

final class _NotificationDetailBody extends StatelessWidget {
  const _NotificationDetailBody({required this.notification});

  final MatchNotification notification;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: <Widget>[
        GlassPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(
                    child: Text(
                      notification.title,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                  ),
                  Icon(
                    notification.isRead
                        ? Icons.mark_email_read_outlined
                        : Icons.mark_email_unread_outlined,
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: <Widget>[
                  _DetailChip(
                    icon: Icons.insights_rounded,
                    label: notification.scoreLabel,
                  ),
                  _DetailChip(
                    icon: Icons.swap_horiz_rounded,
                    label: notification.modeLabel,
                  ),
                  _DetailChip(
                    icon: Icons.event_outlined,
                    label: notification.dateLabel,
                  ),
                ],
              ),
              if (notification.body.isNotEmpty) ...<Widget>[
                const SizedBox(height: 18),
                Text(
                  notification.body,
                  style: Theme.of(
                    context,
                  ).textTheme.bodyLarge?.copyWith(height: 1.7),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        _RelatedDataCard(notification: notification),
        if (_hasFinancialRange(notification)) ...<Widget>[
          const SizedBox(height: 12),
          _FinancialCard(notification: notification),
        ],
      ],
    );
  }

  static bool _hasFinancialRange(MatchNotification item) =>
      item.matchedDepositMin != null ||
      item.matchedDepositMax != null ||
      item.matchedRentMin != null ||
      item.matchedRentMax != null;
}

final class _RelatedDataCard extends StatelessWidget {
  const _RelatedDataCard({required this.notification});

  final MatchNotification notification;

  @override
  Widget build(BuildContext context) => GlassPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'اطلاعات مرتبط',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        _RelatedRow(
          icon: Icons.home_work_outlined,
          label: 'ملک',
          value: notification.propertyTitle,
        ),
        const SizedBox(height: 10),
        _RelatedRow(
          icon: Icons.person_outline_rounded,
          label: 'مشتری',
          value: notification.customerName,
        ),
        if (notification.propertyId != null) ...<Widget>[
          const SizedBox(height: 10),
          _RelatedRow(
            icon: Icons.tag_rounded,
            label: 'شناسه ملک',
            value: persianDigits(notification.propertyId!),
          ),
        ],
      ],
    ),
  );
}

final class _FinancialCard extends StatelessWidget {
  const _FinancialCard({required this.notification});

  final MatchNotification notification;

  @override
  Widget build(BuildContext context) => GlassPanel(
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'بازه مالی تطبیق',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 12),
        if (notification.matchedDepositMin != null ||
            notification.matchedDepositMax != null)
          _RelatedRow(
            icon: Icons.account_balance_wallet_outlined,
            label: 'ودیعه',
            value: _range(
              notification.matchedDepositMin,
              notification.matchedDepositMax,
            ),
          ),
        if (notification.matchedRentMin != null ||
            notification.matchedRentMax != null) ...<Widget>[
          const SizedBox(height: 10),
          _RelatedRow(
            icon: Icons.payments_outlined,
            label: 'اجاره ماهانه',
            value: _range(
              notification.matchedRentMin,
              notification.matchedRentMax,
            ),
          ),
        ],
      ],
    ),
  );

  static String _range(double? min, double? max) {
    final minLabel = min == null ? null : formatToman(min);
    final maxLabel = max == null ? null : formatToman(max);
    if (minLabel == null) return maxLabel ?? 'ثبت نشده';
    if (maxLabel == null || min == max) return minLabel;
    return '$minLabel تا $maxLabel';
  }
}

final class _RelatedRow extends StatelessWidget {
  const _RelatedRow({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: <Widget>[
      Icon(icon, size: 21, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 9),
      Text(
        '$label: ',
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
          fontWeight: FontWeight.w700,
        ),
      ),
      Expanded(
        child: Text(
          value,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
      ),
    ],
  );
}

final class _DetailChip extends StatelessWidget {
  const _DetailChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(11),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Icon(icon, size: 16),
        const SizedBox(width: 5),
        Text(label, style: Theme.of(context).textTheme.labelMedium),
      ],
    ),
  );
}
