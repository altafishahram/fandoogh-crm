import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/core/theme/app_theme.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/properties/presentation/widgets/property_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final dashboardProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  ref.watch(matchSessionProvider);
  ref.watch(matchDataRevisionProvider);
  final store = ref.watch(offlineStoreProvider).scoped();
  try {
    final result = await ApiRepository(
      ref.watch(apiClientProvider),
    ).getOne('/dashboard');
    if (store.isConfigured) {
      await store.mergeRecords('dashboard', <Map<String, dynamic>>[
        <String, dynamic>{...result, 'id': 'dashboard'},
      ]);
    }
    return result;
  } catch (error) {
    final code = apiFailureFrom(error).code;
    if (store.isConfigured &&
        (code == 'NETWORK_UNAVAILABLE' || code == 'NETWORK_TIMEOUT')) {
      final cached = await store.records('dashboard');
      if (cached.isNotEmpty) return cached.first;
    }
    rethrow;
  }
});

final class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboard = ref.watch(dashboardProvider);
    final auth = ref.watch(authControllerProvider);
    final unreadState = ref.watch(matchNotificationUnreadCountProvider);
    final unreadCount = unreadState.hasValue ? unreadState.requireValue : 0;
    final canCreateProperty = auth.can('properties.create');
    final canCreateCustomer = auth.can('customers.create');

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: Directionality(
        textDirection: TextDirection.rtl,
        child: dashboard.when(
          loading: () => const _DashboardLoading(),
          error: (error, _) => ErrorState(
            error: error,
            onRetry: () => ref.invalidate(dashboardProvider),
          ),
          data: (data) => RefreshIndicator(
            color: Theme.of(context).colorScheme.primary,
            onRefresh: () =>
                ref.refresh(dashboardProvider.future).then<void>((_) {}),
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: <Widget>[
                SliverToBoxAdapter(
                  child: _DashboardHeader(
                    displayName: auth.displayName,
                    unreadCount: unreadCount,
                    onProfile: () => context.push('/profile'),
                    onNotifications: () => context.push('/match-notifications'),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate(<Widget>[
                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.storefront_outlined),
                          title: const Text('آگهی‌های عمومی و همکاری آژانس‌ها'),
                          onTap: () => context.push('/marketplace'),
                        ),
                      ),
                      Card(
                        child: ListTile(
                          leading: const Icon(Icons.chat_bubble_outline),
                          title: const Text('گفت‌وگوهای آگهی‌ها'),
                          onTap: () => context.push('/conversations'),
                        ),
                      ),
                      _SearchLauncher(onTap: () => context.push('/search')),
                      const SizedBox(height: 18),
                      _MetricsRow(data: data),
                      const SizedBox(height: 18),
                      _QuickActions(
                        canCreateProperty: canCreateProperty,
                        canCreateCustomer: canCreateCustomer,
                      ),
                      const SizedBox(height: 24),
                      _SectionHeading(
                        title: 'ملک‌های اخیر',
                        actionLabel: 'مشاهده همه',
                        onAction: () => context.go('/properties'),
                      ),
                      const SizedBox(height: 10),
                      _RecentProperties(
                        items: data['recent_properties'],
                        onTap: (id) => context.push('/properties/$id'),
                      ),
                      const SizedBox(height: 24),
                      _RecentNotes(items: data['recent_notes']),
                    ]),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

final class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({
    required this.displayName,
    required this.unreadCount,
    required this.onProfile,
    required this.onNotifications,
  });

  final String displayName;
  final int unreadCount;
  final VoidCallback onProfile;
  final VoidCallback onNotifications;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
          colors: <Color>[
            Color(0xFF115E59),
            Color(0xFF0F766E),
            Color(0xFF17988C),
          ],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'دفتر املاکی',
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(
                                color: colors.onPrimary,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        Text(
                          'داشبورد مدیریت املاک',
                          style: Theme.of(context).textTheme.labelMedium
                              ?.copyWith(
                                color: colors.onPrimary.withValues(alpha: .78),
                              ),
                        ),
                      ],
                    ),
                  ),
                  _HeaderAction(
                    tooltip: 'اعلان‌های تطبیق',
                    onPressed: onNotifications,
                    badge: unreadCount,
                    icon: Icons.notifications_none_rounded,
                  ),
                  const SizedBox(width: 8),
                  _HeaderAction(
                    tooltip: 'حساب کاربری',
                    onPressed: onProfile,
                    icon: Icons.person_outline_rounded,
                  ),
                ],
              ),
              const SizedBox(height: 20),
              Text(
                'سلام $displayName',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: colors.onPrimary,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'به داشبورد دفتر املاکی خوش آمدید',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: colors.onPrimary.withValues(alpha: .82),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _HeaderAction extends StatelessWidget {
  const _HeaderAction({
    required this.tooltip,
    required this.onPressed,
    required this.icon,
    this.badge = 0,
  });

  final String tooltip;
  final VoidCallback onPressed;
  final IconData icon;
  final int badge;

  @override
  Widget build(BuildContext context) => Badge(
    isLabelVisible: badge > 0,
    label: Text(badge > 99 ? '۹۹+' : persianDigits(badge)),
    child: IconButton.filledTonal(
      tooltip: tooltip,
      onPressed: onPressed,
      style: IconButton.styleFrom(
        minimumSize: const Size.square(44),
        foregroundColor: Colors.white,
        backgroundColor: Colors.white.withValues(alpha: .13),
      ),
      icon: Icon(icon),
    ),
  );
}

final class _SearchLauncher extends StatelessWidget {
  const _SearchLauncher({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return SearchBar(
      readOnly: true,
      onTap: onTap,
      hintText: 'جستجوی ملک، مشتری یا کد',
      leading: Icon(Icons.search_rounded, color: colors.primary),
      trailing: <Widget>[
        Icon(Icons.tune_rounded, color: colors.onSurfaceVariant),
      ],
      elevation: const WidgetStatePropertyAll(0),
      backgroundColor: WidgetStatePropertyAll(colors.surface),
      shape: WidgetStatePropertyAll(
        RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: colors.outlineVariant),
        ),
      ),
    );
  }
}

final class _MetricsRow extends StatelessWidget {
  const _MetricsRow({required this.data});

  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final statuses = _statusCounts(data['properties_by_status']);
    final activeProperties =
        (statuses['available'] ?? 0) + (statuses['reserved'] ?? 0);
    final activeCustomers = _asInt(data['active_customers']);
    final unassigned = _asInt(data['unassigned_properties']);
    return Row(
      children: <Widget>[
        Expanded(
          child: _MetricCard(
            label: 'املاک فعال',
            value: activeProperties,
            suffix: 'ملک',
            icon: Icons.home_work_outlined,
            color: Theme.of(context).colorScheme.primary,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _MetricCard(
            label: 'مشتریان',
            value: activeCustomers,
            suffix: 'نفر',
            icon: Icons.people_outline_rounded,
            color: AppTheme.violet,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _MetricCard(
            label: 'بدون مسئول',
            value: unassigned,
            suffix: 'ملک',
            icon: Icons.assignment_late_outlined,
            color: AppTheme.orange,
          ),
        ),
      ],
    );
  }

  static Map<String, int> _statusCounts(Object? value) {
    if (value is! Map) return const <String, int>{};
    return value.map((key, item) => MapEntry('$key', _asInt(item)));
  }

  static int _asInt(Object? value) =>
      value is num ? value.toInt() : int.tryParse('$value') ?? 0;
}

final class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.canCreateProperty,
    required this.canCreateCustomer,
  });

  final bool canCreateProperty;
  final bool canCreateCustomer;

  @override
  Widget build(BuildContext context) {
    final actions = <_QuickActionData>[
      if (canCreateProperty)
        const _QuickActionData(
          'ثبت ملک جدید',
          'افزودن پرونده ملک',
          Icons.add_home_work_outlined,
          AppTheme.primary,
          '/properties/new',
        ),
      if (canCreateCustomer)
        const _QuickActionData(
          'ثبت مشتری جدید',
          'افزودن نیاز مشتری',
          Icons.person_add_alt_1_outlined,
          AppTheme.violet,
          '/customers/new',
        ),
      const _QuickActionData(
        'فهرست املاک',
        'جست‌وجو و فیلتر',
        Icons.apartment_outlined,
        AppTheme.blue,
        '/properties',
      ),
      const _QuickActionData(
        'فهرست مشتریان',
        'پیگیری درخواست‌ها',
        Icons.people_outline_rounded,
        AppTheme.orange,
        '/customers',
      ),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'دسترسی سریع',
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        LayoutBuilder(
          builder: (context, constraints) {
            const gap = 10.0;
            final width = (constraints.maxWidth - gap) / 2;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: actions
                  .map(
                    (action) => SizedBox(
                      width: width,
                      child: _QuickAction(data: action),
                    ),
                  )
                  .toList(growable: false),
            );
          },
        ),
      ],
    );
  }
}

final class _QuickAction extends StatelessWidget {
  const _QuickAction({required this.data});

  final _QuickActionData data;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: () => context.push(data.route),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: <Widget>[
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: data.color.withValues(alpha: .11),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(data.icon, color: data.color),
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    data.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  Text(
                    data.subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

final class _QuickActionData {
  const _QuickActionData(
    this.title,
    this.subtitle,
    this.icon,
    this.color,
    this.route,
  );

  final String title;
  final String subtitle;
  final IconData icon;
  final Color color;
  final String route;
}

final class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.suffix,
    required this.icon,
    required this.color,
  });

  final String label;
  final int value;
  final String suffix;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      color: color.withValues(alpha: .09),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
        side: BorderSide(color: color.withValues(alpha: .22)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
        child: Column(
          children: <Widget>[
            CircleAvatar(
              radius: 22,
              backgroundColor: color,
              foregroundColor: Colors.white,
              child: Icon(icon, size: 22),
            ),
            const SizedBox(height: 8),
            Text(
              label,
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: colors.onSurface,
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              persianDigits('$value'),
              style: TextStyle(
                color: colors.onSurface,
                fontSize: 22,
                fontWeight: FontWeight.w900,
                height: 1.1,
              ),
            ),
            Text(
              suffix,
              style: TextStyle(
                color: colors.onSurfaceVariant,
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _SectionHeading extends StatelessWidget {
  const _SectionHeading({
    required this.title,
    required this.actionLabel,
    required this.onAction,
  });

  final String title;
  final String actionLabel;
  final VoidCallback onAction;

  @override
  Widget build(BuildContext context) => Row(
    children: <Widget>[
      Expanded(
        child: Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
      ),
      TextButton(onPressed: onAction, child: Text(actionLabel)),
    ],
  );
}

final class _RecentProperties extends ConsumerWidget {
  const _RecentProperties({required this.items, required this.onTap});

  final Object? items;
  final void Function(int id) onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final values = items is List
        ? (items as List)
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList(growable: false)
        : const <Map<String, dynamic>>[];
    if (values.isEmpty) {
      return const _EmptyCard(
        icon: Icons.home_work_outlined,
        message: 'هنوز ملکی ثبت نشده است.',
      );
    }

    final token = ref.read(apiClientProvider).token;
    final headers = token == null || token.isEmpty
        ? null
        : <String, String>{'Authorization': 'Bearer $token'};
    return Column(
      children: <Widget>[
        for (var index = 0; index < values.length; index++) ...<Widget>[
          PropertyCard.fromMap(
            values[index],
            imageHeaders: headers,
            onTap: () => onTap(_idOf(values[index])),
            display: PropertyCardDisplay.classic,
          ),
          if (index < values.length - 1) const SizedBox(height: 10),
        ],
      ],
    );
  }

  static int _idOf(Map<String, dynamic> value) =>
      (value['id'] as num?)?.toInt() ?? 0;
}

final class _RecentNotes extends StatelessWidget {
  const _RecentNotes({required this.items});

  final Object? items;

  @override
  Widget build(BuildContext context) {
    final values = items is List
        ? (items as List).whereType<Map>().toList(growable: false)
        : const <Map>[];
    if (values.isEmpty) return const SizedBox.shrink();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Text(
          'یادداشت‌های اخیر',
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        Card(
          child: Column(
            children: <Widget>[
              for (var index = 0; index < values.length; index++) ...<Widget>[
                ListTile(
                  leading: const CircleAvatar(
                    child: Icon(Icons.notes_rounded, size: 18),
                  ),
                  title: Text(
                    '${values[index]['body'] ?? ''}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  subtitle: Text('${values[index]['property_title'] ?? ''}'),
                  onTap: () {
                    final id = (values[index]['property_id'] as num?)?.toInt();
                    if (id != null) context.push('/properties/$id');
                  },
                ),
                if (index < values.length - 1) const Divider(height: 1),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

final class _EmptyCard extends StatelessWidget {
  const _EmptyCard({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: <Widget>[
          Icon(icon, size: 36, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 8),
          Text(message),
        ],
      ),
    ),
  );
}

final class _DashboardLoading extends StatelessWidget {
  const _DashboardLoading();

  @override
  Widget build(BuildContext context) =>
      const Center(child: CircularProgressIndicator());
}
