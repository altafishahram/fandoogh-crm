import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// The total is retained when a match is read; only the personal unread number
/// changes. Unknown old/offline summaries remain visibly unknown.
final class RelatedMatchBadge extends StatelessWidget {
  const RelatedMatchBadge({
    required this.scope,
    required this.summary,
    super.key,
  });
  final RelatedMatchScope scope;
  final MatchSummary? summary;

  @override
  Widget build(BuildContext context) {
    final value = summary;
    final label = value == null
        ? 'تطبیق‌ها · نامشخص'
        : '${persianDigits(value.count)} تطبیق'
              '${value.unreadCount > 0 ? ' · ${persianDigits(value.unreadCount)} جدید' : ''}';
    return Semantics(
      label: value == null
          ? 'تعداد تطبیق‌ها نامشخص است؛ مشاهده تطبیق‌های مرتبط'
          : '${persianDigits(value.count)} تطبیق مرتبط، ${persianDigits(value.unreadCount)} خوانده نشده؛ مشاهده فهرست',
      button: true,
      excludeSemantics: true,
      child: FilledButton.tonalIcon(
        style: FilledButton.styleFrom(
          minimumSize: const Size(48, 48),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        ),
        onPressed: scope.id > 0 ? () => context.push(scope.path) : null,
        icon: Icon(
          value != null && value.unreadCount > 0
              ? Icons.notifications_active_outlined
              : Icons.compare_arrows_rounded,
          size: 20,
        ),
        label: Text(label, textAlign: TextAlign.start),
      ),
    );
  }
}
