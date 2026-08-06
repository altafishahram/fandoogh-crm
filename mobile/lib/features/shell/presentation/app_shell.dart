import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

final class AppShell extends StatelessWidget {
  const AppShell({required this.child, super.key});

  final Widget child;

  static const _destinations = <_Destination>[
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
    ),
    _Destination('/customers', 'مشتریان', Icons.people_outline, Icons.people),
    _Destination('/report', 'گزارش', Icons.bar_chart_outlined, Icons.bar_chart),
    _Destination('/profile', 'حساب', Icons.person_outline, Icons.person),
  ];

  @override
  Widget build(BuildContext context) {
    final path = GoRouterState.of(context).uri.path;
    final index = _destinations.indexWhere(
      (item) => path.startsWith(item.path),
    );
    final selected = index < 0 ? 0 : index;

    return Scaffold(
      body: child,
      bottomNavigationBar: ClipRRect(
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 18, sigmaY: 18),
          child: NavigationBar(
            selectedIndex: selected,
            onDestinationSelected: (value) =>
                context.go(_destinations[value].path),
            destinations: _destinations
                .map(
                  (item) => NavigationDestination(
                    icon: Icon(item.icon),
                    selectedIcon: Icon(item.selectedIcon),
                    label: item.label,
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ),
    );
  }
}

final class _Destination {
  const _Destination(this.path, this.label, this.icon, this.selectedIcon);
  final String path;
  final String label;
  final IconData icon;
  final IconData selectedIcon;
}
