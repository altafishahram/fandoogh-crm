import 'package:fandoogh_crm/core/theme/app_theme.dart';
import 'package:fandoogh_crm/features/public_auth/public_auth.dart';
import 'package:fandoogh_crm/features/marketplace/presentation/marketplace_pages.dart';
import 'package:fandoogh_crm/features/chat/chat_pages.dart';
import 'package:fandoogh_crm/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final publicRouterProvider = Provider<GoRouter>((ref) {
  final session = ref.watch(publicSessionProvider);
  final router = GoRouter(
    initialLocation: '/marketplace',
    redirect: (_, state) {
      if (session.isLoading) {
        return state.uri.path == '/loading' ? null : '/loading';
      }
      if (session.value == null) {
        return state.uri.path == '/login' ? null : '/login';
      }
      if (state.uri.path == '/login' || state.uri.path == '/loading') {
        return '/marketplace';
      }
      return null;
    },
    routes: [
      GoRoute(
        path: '/loading',
        builder: (_, _) =>
            const Scaffold(body: Center(child: CircularProgressIndicator())),
      ),
      GoRoute(path: '/login', builder: (_, _) => const PublicLoginPage()),
      GoRoute(path: '/account', builder: (_, _) => const PublicAccountPage()),
      GoRoute(
        path: '/marketplace',
        builder: (_, _) => const MarketplacePage(publicOnly: true),
      ),
      GoRoute(
        path: '/marketplace/:id',
        builder: (_, state) => ListingDetailPage(
          id: int.parse(state.pathParameters['id']!),
          audience: 'public',
        ),
      ),
      ...chatRoutes,
    ],
  );
  ref.onDispose(router.dispose);
  return router;
});

final class PublicApp extends ConsumerWidget {
  const PublicApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => MaterialApp.router(
    debugShowCheckedModeBanner: false,
    title: 'دفتر املاکی عمومی',
    locale: const Locale('fa'),
    theme: AppTheme.light,
    darkTheme: AppTheme.dark,
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: AppLocalizations.supportedLocales,
    routerConfig: ref.watch(publicRouterProvider),
  );
}
