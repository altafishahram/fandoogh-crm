import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:fandoogh_crm/features/match_notifications/data/related_matches_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class RelatedMatchesPage extends ConsumerStatefulWidget {
  const RelatedMatchesPage({required this.scope, super.key});
  final RelatedMatchScope scope;

  @override
  ConsumerState<RelatedMatchesPage> createState() => _RelatedMatchesPageState();
}

final class _RelatedMatchesPageState extends ConsumerState<RelatedMatchesPage> {
  bool _loadingMore = false;

  @override
  Widget build(BuildContext context) {
    final result = ref.watch(relatedMatchesProvider(widget.scope));
    final controller = ref.read(relatedMatchesProvider(widget.scope).notifier);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.scope.side == RelatedMatchSide.property
              ? 'مشتریان مرتبط با ملک'
              : 'املاک مرتبط با مشتری',
        ),
      ),
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: SafeArea(
          top: false,
          child: result.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) =>
                ErrorState(error: error, onRetry: controller.refresh),
            data: (page) => RefreshIndicator(
              onRefresh: controller.refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                children: <Widget>[
                  if (page.fromCache) ...<Widget>[
                    GlassPanel(
                      child: Text(
                        page.total == null
                            ? 'آفلاین هستید؛ فهرست این مورد هنوز ذخیره نشده است. پس از اتصال دوباره تلاش کنید.'
                            : 'فهرست ذخیره‌شده نمایش داده می‌شود؛ برای تازه‌سازی به اینترنت متصل شوید.',
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  if (page.total != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Text(
                        '${persianDigits(page.total!)} تطبیق مرتبط'
                        '${page.unreadCount == null ? '' : ' · ${persianDigits(page.unreadCount!)} خوانده نشده'}',
                      ),
                    ),
                  if (page.items.isEmpty &&
                      (!page.fromCache || page.total != null))
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 48),
                      child: Text(
                        'تطبیق معتبری برای این مورد وجود ندارد.',
                        textAlign: TextAlign.center,
                      ),
                    ),
                  for (final item in page.items) ...<Widget>[
                    _RelatedMatchCard(
                      key: ValueKey(item.id),
                      item: item,
                      side: widget.scope.side,
                    ),
                    const SizedBox(height: 12),
                  ],
                  if (page.hasMore && page.items.length < 20)
                    OutlinedButton.icon(
                      onPressed: _loadingMore
                          ? null
                          : () => _loadMore(controller),
                      icon: _loadingMore
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.expand_more_rounded),
                      label: const Text('نمایش بیشتر'),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _loadMore(RelatedMatchesController controller) async {
    setState(() => _loadingMore = true);
    try {
      await controller.loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

final class _RelatedMatchCard extends StatelessWidget {
  const _RelatedMatchCard({required this.item, required this.side, super.key});
  final MatchNotification item;
  final RelatedMatchSide side;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return GlassPanel(
      padding: EdgeInsets.zero,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: () => context.push('/match-notifications/${item.id}'),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                side == RelatedMatchSide.property
                    ? item.customerName
                    : item.propertyTitle,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  Text(item.scoreLabel, style: theme.textTheme.labelLarge),
                  Text(item.modeLabel),
                  Text(
                    item.isRead ? 'خوانده شده' : 'خوانده نشده',
                    style: TextStyle(color: theme.colorScheme.primary),
                  ),
                ],
              ),
              if (item.shortReason.isNotEmpty) ...<Widget>[
                const SizedBox(height: 8),
                Text(
                  item.shortReason,
                  style: theme.textTheme.bodyMedium?.copyWith(height: 1.6),
                ),
              ],
              const SizedBox(height: 8),
              const Text('مشاهده جزئیات اعلان'),
            ],
          ),
        ),
      ),
    );
  }
}
