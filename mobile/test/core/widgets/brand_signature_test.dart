import 'package:fandoogh_crm/core/theme/app_theme.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:fandoogh_crm/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('application title uses the new brand in every supported locale', () {
    for (final locale in AppLocalizations.supportedLocales) {
      final labels = lookupAppLocalizations(locale);
      expect(labels.appName, 'دفتر املاکی');
      expect(labels.foundationIconLabel, 'نشان سامانه دفتر املاکی');
    }
  });

  for (final brightness in Brightness.values) {
    for (final compact in <bool>[false, true]) {
      testWidgets(
        'new brand remains readable in $brightness with compact=$compact',
        (tester) async {
          final semantics = tester.ensureSemantics();
          tester.view.devicePixelRatio = 1;
          addTearDown(tester.view.resetDevicePixelRatio);
          addTearDown(tester.view.resetPhysicalSize);

          try {
            for (final size in <Size>[
              const Size(320, 640),
              const Size(640, 320),
            ]) {
              tester.view.physicalSize = size;
              await tester.pumpWidget(
                MaterialApp(
                  theme: brightness == Brightness.dark
                      ? AppTheme.dark
                      : AppTheme.light,
                  home: MediaQuery(
                    data: MediaQueryData(
                      size: size,
                      textScaler: const TextScaler.linear(2),
                    ),
                    child: Directionality(
                      textDirection: TextDirection.rtl,
                      child: Scaffold(
                        body: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Center(
                            child: BrandSignature(compact: compact),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              );
              await tester.pumpAndSettle();

              expect(find.text('دفتر املاکی'), findsOneWidget);
              expect(find.text('طراحی‌شده توسط فندوق استودیو'), findsOneWidget);
              expect(find.text('د'), findsOneWidget);
              expect(find.text('ملک بان'), findsNothing);
              expect(
                find.bySemanticsLabel(
                  'دفتر املاکی، طراحی‌شده توسط فندوق استودیو',
                ),
                findsOneWidget,
              );
              expect(tester.takeException(), isNull);
            }
          } finally {
            semantics.dispose();
          }
        },
      );
    }
  }
}
