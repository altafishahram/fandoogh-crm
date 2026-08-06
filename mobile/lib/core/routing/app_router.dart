import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/auth/auth_state.dart';
import 'package:fandoogh_crm/features/auth/presentation/change_password_page.dart';
import 'package:fandoogh_crm/features/auth/presentation/login_page.dart';
import 'package:fandoogh_crm/features/auth/presentation/splash_page.dart';
import 'package:fandoogh_crm/features/auth/presentation/suspended_page.dart';
import 'package:fandoogh_crm/features/dashboard/presentation/dashboard_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customer_detail_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customer_form_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customers_page.dart';
import 'package:fandoogh_crm/features/profile/presentation/profile_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/properties_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/property_detail_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/property_form_page.dart';
import 'package:fandoogh_crm/features/reports/presentation/report_page.dart';
import 'package:fandoogh_crm/features/search/presentation/global_search_page.dart';
import 'package:fandoogh_crm/features/shell/presentation/app_shell.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final appRouterProvider = Provider<GoRouter>((ref) {
  final auth = ref.watch(authControllerProvider);
  final router = GoRouter(
    initialLocation: '/splash',
    redirect: (context, state) => _redirect(auth, state.uri.path),
    routes: <RouteBase>[
      GoRoute(path: '/splash', builder: (_, _) => const SplashPage()),
      GoRoute(path: '/login', builder: (_, _) => const LoginPage()),
      GoRoute(
        path: '/change-password',
        builder: (_, _) => const ChangePasswordPage(),
      ),
      GoRoute(path: '/suspended', builder: (_, _) => const SuspendedPage()),
      ShellRoute(
        builder: (_, _, child) => AppShell(child: child),
        routes: <RouteBase>[
          GoRoute(path: '/dashboard', builder: (_, _) => const DashboardPage()),
          GoRoute(
            path: '/properties',
            builder: (_, _) => const PropertiesPage(),
            routes: <RouteBase>[
              GoRoute(path: 'new', builder: (_, _) => const PropertyFormPage()),
              GoRoute(
                path: ':id',
                builder: (_, state) => PropertyDetailPage(
                  propertyId: int.parse(state.pathParameters['id']!),
                ),
                routes: <RouteBase>[
                  GoRoute(
                    path: 'edit',
                    builder: (_, state) => PropertyFormPage(
                      propertyId: int.parse(state.pathParameters['id']!),
                    ),
                  ),
                ],
              ),
            ],
          ),
          GoRoute(
            path: '/customers',
            builder: (_, _) => const CustomersPage(),
            routes: <RouteBase>[
              GoRoute(path: 'new', builder: (_, _) => const CustomerFormPage()),
              GoRoute(
                path: ':id',
                builder: (_, state) => CustomerDetailPage(
                  customerId: int.parse(state.pathParameters['id']!),
                ),
                routes: <RouteBase>[
                  GoRoute(
                    path: 'edit',
                    builder: (_, state) => CustomerFormPage(
                      customerId: int.parse(state.pathParameters['id']!),
                    ),
                  ),
                ],
              ),
            ],
          ),
          GoRoute(path: '/report', builder: (_, _) => const ReportPage()),
          GoRoute(path: '/profile', builder: (_, _) => const ProfilePage()),
          GoRoute(path: '/search', builder: (_, _) => const GlobalSearchPage()),
        ],
      ),
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});

String? _redirect(AuthState auth, String path) {
  if (auth.status == AuthStatus.restoring) {
    return path == '/splash' ? null : '/splash';
  }
  if (auth.status == AuthStatus.signedOut) {
    return path == '/login' ? null : '/login';
  }
  if (auth.status == AuthStatus.passwordChangeRequired) {
    return path == '/change-password' ? null : '/change-password';
  }
  if (auth.status == AuthStatus.agencySuspended) {
    return path == '/suspended' ? null : '/suspended';
  }
  if (path == '/splash' || path == '/login' || path == '/change-password') {
    return '/dashboard';
  }
  return null;
}
