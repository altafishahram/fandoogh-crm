import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';

final class SplashPage extends StatelessWidget {
  const SplashPage({super.key});

  @override
  Widget build(BuildContext context) => const Scaffold(
    body: SafeArea(
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            BrandSignature(),
            SizedBox(height: 28),
            CircularProgressIndicator(),
            SizedBox(height: 16),
            Text('در حال بازیابی نشست…'),
          ],
        ),
      ),
    ),
  );
}
