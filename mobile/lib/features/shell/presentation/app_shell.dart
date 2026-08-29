import 'dart:async';

import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/widgets/sync_banner.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AppShell extends ConsumerStatefulWidget {
  const AppShell({required this.child, super.key});

  final Widget child;

  @override
  ConsumerState<AppShell> createState() => _AppShellState();
}

final class _AppShellState extends ConsumerState<AppShell>
    with WidgetsBindingObserver {
  Timer? _notificationTimer;

  static const _allDestinations = <_Destination>[
    _Destination(
      '/dashboard',
      'خانه',
      Icons.dashboard_outlined,
      Icons.dashboard,
    ),
    _Destination(
      '/properties',
      'املاک',
      Icons.apartment_outlined,
      Icons.apartment,
      permission: 'properties.view',
    ),
    _Destination(
      '/customers',
      'مشتریان',
      Icons.people_outline,
      Icons.people,
      permission: 'customers.view',
    ),
    _Destination(
      '/report',
      'گزارش',
      Icons.bar_chart_outlined,
      Icons.bar_chart,
      report: true,
    ),
    _Destination('/profile', 'حساب', Icons.person_outline, Icons.person),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => _refreshNotifications(),
    );
    _notificationTimer = Timer.periodic(
      const Duration(seconds: 30),
      (_) => _refreshNotifications(),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _refreshNotifications();
  }

  @override
  void dispose() {
    _notificationTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _refreshNotifications() {
    if (!mounted) return;
    ref.invalidate(matchNotificationUnreadCountProvider);
    ref.invalidate(matchNotificationsProvider);
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final unreadState = ref.watch(matchNotificationUnreadCountProvider);
    final unreadCount = unreadState.value ?? 0;
    ref.listen(matchNotificationUnreadCountProvider, (previous, next) {
      final before = previous?.value;
      final current = next.value;
      if (before == null || current == null || current <= before || !mounted) {
        return;
      }
      final added = current - before;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Row(
              children: <Widget>[
                const Icon(Icons.notifications_active_rounded),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    '${persianDigits(added)} اعلان تطبیق جدید دریافت شد.',
                  ),
                ),
              ],
            ),
            action: SnackBarAction(
              label: 'مشاهده',
              onPressed: () => context.push('/match-notifications'),
            ),
          ),
        );
    });
    final destinations = _allDestinations
        .where((item) {
          if (item.report) {
            return auth.can('reports.agency.view') ||
                auth.can('reports.own.view');
          }
          return item.permission == null || auth.can(item.permission!);
        })
        .toList(growable: false);
    final path = GoRouterState.of(context).uri.path;
    final index = destinations.indexWhere((item) => path.startsWith(item.path));
    final selected = index < 0 ? 0 : index;

    return Scaffold(
      body: widget.child,
      bottomNavigationBar: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const SyncBanner(),
          NavigationBar(
            selectedIndex: selected,
            onDestinationSelected: (value) =>
                context.go(destinations[value].path),
            destinations: destinations
                .map(
                  (item) => NavigationDestination(
                    icon: item.path == '/dashboard' && unreadCount > 0
                        ? Badge(
                            label: Text(
                              unreadCount > 99
                                  ? '۹۹+'
                                  : persianDigits(unreadCount),
                            ),
                            child: Icon(item.icon),
                          )
                        : Icon(item.icon),
                    selectedIcon: item.path == '/dashboard' && unreadCount > 0
                        ? Badge(
                            label: Text(
                              unreadCount > 99
                                  ? '۹۹+'
                                  : persianDigits(unreadCount),
                            ),
                            child: Icon(item.selectedIcon),
                          )
                        : Icon(item.selectedIcon),
                    label: item.label,
                  ),
                )
                .toList(growable: false),
          ),
        ],
      ),
    );
  }
}

final class _Destination {
  const _Destination(
    this.path,
    this.label,
    this.icon,
    this.selectedIcon, {
    this.permission,
    this.report = false,
  });
  final String path;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
  final String? permission;
  final bool report;
}
