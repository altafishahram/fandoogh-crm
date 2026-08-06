import 'package:fandoogh_crm/core/routing/app_router.dart';
import 'package:fandoogh_crm/core/theme/app_theme.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:fandoogh_crm/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class FandooghApp extends ConsumerWidget {
  const FandooghApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(appRouterProvider);

    return MaterialApp.router(
      debugShowCheckedModeBanner: false,
      locale: const Locale('fa'),
      onGenerateTitle: (context) => AppLocalizations.of(context).appName,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: AppLocalizations.supportedLocales,
      builder: (context, child) =>
          GlassBackdrop(child: child ?? const SizedBox.shrink()),
      routerConfig: router,
    );
  }
}
