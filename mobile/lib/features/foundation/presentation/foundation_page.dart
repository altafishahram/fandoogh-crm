import 'package:fandoogh_crm/l10n/app_localizations.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';

final class FoundationPage extends StatelessWidget {
  const FoundationPage({super.key});

  @override
  Widget build(BuildContext context) {
    final localizations = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(title: Text(localizations.appName)),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: GlassPanel(
                padding: const EdgeInsets.all(28),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: <Widget>[
                    const BrandSignature(),
                    const SizedBox(height: 24),
                    Text(
                      localizations.foundationTitle,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      localizations.foundationDescription,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                    const SizedBox(height: 24),
                    Chip(
                      avatar: const Icon(Icons.check_circle_outline),
                      label: Text(localizations.foundationStatus),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
