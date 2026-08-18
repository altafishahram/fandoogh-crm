import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/widgets/sync_banner.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AppShell extends ConsumerWidget {
  const AppShell({required this.child, super.key});

  final Widget child;

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
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
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
      body: child,
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
                    icon: Icon(item.icon),
                    selectedIcon: Icon(item.selectedIcon),
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
