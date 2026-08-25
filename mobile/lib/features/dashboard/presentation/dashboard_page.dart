import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/config/app_config.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final dashboardProvider = FutureProvider<Map<String, dynamic>>((ref) {
  return ApiRepository(ref.watch(apiClientProvider)).getOne('/dashboard');
});

final class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboard = ref.watch(dashboardProvider);
    final auth = ref.watch(authControllerProvider);
    final canCreateProperty = auth.can('properties.create');

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      floatingActionButton: canCreateProperty
          ? FloatingActionButton(
              tooltip: 'ثبت ملک جدید',
              onPressed: () => context.push('/properties/new'),
              child: const Icon(Icons.add_rounded),
            )
          : null,
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
                    onProfile: () => context.push('/profile'),
                    onNotifications: () => _showComingSoon(context),
                  ),
                ),
                SliverPadding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                  sliver: SliverList(
                    delegate: SliverChildListDelegate(<Widget>[
                      _SearchLauncher(onTap: () => context.push('/search')),
                      const SizedBox(height: 18),
                      _MetricsRow(data: data),
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

  static void _showComingSoon(BuildContext context) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text('اعلان تازه‌ای وجود ندارد.')));
  }
}

final class _DashboardHeader extends StatelessWidget {
  const _DashboardHeader({
    required this.displayName,
    required this.onProfile,
    required this.onNotifications,
  });

  final String displayName;
  final VoidCallback onProfile;
  final VoidCallback onNotifications;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.primary,
        borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
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
                          'ملک بان',
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
                  IconButton(
                    tooltip: 'اعلان‌ها',
                    onPressed: onNotifications,
                    color: colors.onPrimary,
                    icon: const Icon(Icons.notifications_none_rounded),
                  ),
                  IconButton(
                    tooltip: 'حساب کاربری',
                    onPressed: onProfile,
                    color: colors.onPrimary,
                    icon: const Icon(Icons.account_circle_outlined),
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
                'به داشبورد ملک بان خوش آمدید',
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
            color: const Color(0xFF2AA6A6),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _MetricCard(
            label: 'بدون مسئول',
            value: unassigned,
            suffix: 'ملک',
            icon: Icons.assignment_late_outlined,
            color: const Color(0xFFE19A1A),
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
          _DashboardPropertyCard(
            property: values[index],
            imageHeaders: headers,
            onTap: () => onTap(_idOf(values[index])),
          ),
          if (index < values.length - 1) const SizedBox(height: 10),
        ],
      ],
    );
  }

  static int _idOf(Map<String, dynamic> value) =>
      (value['id'] as num?)?.toInt() ?? 0;
}

final class _DashboardPropertyCard extends StatelessWidget {
  const _DashboardPropertyCard({
    required this.property,
    required this.imageHeaders,
    required this.onTap,
  });

  final Map<String, dynamic> property;
  final Map<String, String>? imageHeaders;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final transaction = '${property['transaction_type'] ?? 'sale'}';
    final isRent = transaction == 'rent';
    final status = '${property['status'] ?? ''}';
    final title = '${property['title'] ?? 'بدون عنوان'}';
    final area = property['area_sqm'] ?? property['building_area'];
    final price = isRent
        ? property['deposit_amount'] ?? property['monthly_rent']
        : property['sale_price'];
    final imageUrl = property['cover_image_url'] as String?;

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Row(
            textDirection: TextDirection.rtl,
            children: <Widget>[
              _DashboardPropertyImage(
                imageUrl: imageUrl,
                imageHeaders: imageHeaders,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        const Icon(Icons.chevron_left_rounded, size: 20),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 6,
                      runSpacing: 5,
                      children: <Widget>[
                        _StatusChip(
                          label: _transactionLabel(transaction),
                          color: isRent
                              ? const Color(0xFF008A8F)
                              : const Color(0xFFE19A1A),
                        ),
                        if (status.isNotEmpty)
                          _StatusChip(
                            label: _statusLabel(status),
                            color: colors.primary,
                            soft: true,
                          ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${_areaLabel(area)} • ${_propertyCode(property['code'])}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.onSurfaceVariant,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      isRent
                          ? 'ودیعه ${formatToman(price)}'
                          : formatToman(price),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: colors.primary,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
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

  static String _transactionLabel(String value) => switch (value) {
    'rent' => 'اجاره',
    'partnership' => 'مشارکت',
    _ => 'فروش',
  };

  static String _statusLabel(String value) => switch (value) {
    'available' => 'موجود',
    'reserved' => 'رزرو',
    'sold' => 'فروخته‌شده',
    'rented' => 'اجاره‌رفته',
    _ => value,
  };

  static String _areaLabel(Object? value) {
    final area = num.tryParse('$value');
    if (area == null) return 'متراژ نامشخص';
    final display = area % 1 == 0 ? area.toInt().toString() : '$area';
    return '${persianDigits(display)} متر';
  }

  static String _propertyCode(Object? value) {
    final code = '$value'.trim();
    return code.isEmpty || code == 'null' ? 'کد ثبت نشده' : code;
  }
}

final class _StatusChip extends StatelessWidget {
  const _StatusChip({
    required this.label,
    required this.color,
    this.soft = false,
  });

  final String label;
  final Color color;
  final bool soft;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: soft ? color.withValues(alpha: .11) : color,
      borderRadius: BorderRadius.circular(8),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      child: Text(
        label,
        style: TextStyle(
          color: soft ? color : Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w900,
        ),
      ),
    ),
  );
}

final class _DashboardPropertyImage extends StatelessWidget {
  const _DashboardPropertyImage({
    required this.imageUrl,
    required this.imageHeaders,
  });

  final String? imageUrl;
  final Map<String, String>? imageHeaders;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 104,
    height: 104,
    child: ClipRRect(borderRadius: BorderRadius.circular(16), child: _image()),
  );

  Widget _image() {
    final value = imageUrl?.trim();
    if (value == null || value.isEmpty) return const _ImagePlaceholder();
    final uri = Uri.tryParse(value);
    final base = Uri.parse(AppConfig.apiBaseUrl);
    final url = uri == null || !uri.hasScheme || uri.host.isEmpty
        ? base.resolve(value.startsWith('/') ? value : '/$value').toString()
        : value;
    return Image.network(
      url,
      fit: BoxFit.cover,
      headers: imageHeaders,
      errorBuilder: (_, _, _) => const _ImagePlaceholder(),
    );
  }
}

final class _ImagePlaceholder extends StatelessWidget {
  const _ImagePlaceholder();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return ColoredBox(
      color: colors.primary.withValues(alpha: .08),
      child: Center(
        child: Icon(Icons.home_work_outlined, color: colors.primary, size: 32),
      ),
    );
  }
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
