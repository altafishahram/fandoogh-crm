import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class SuspendedPage extends ConsumerWidget {
  const SuspendedPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    body: SafeArea(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: GlassPanel(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: <Widget>[
                  const BrandSignature(),
                  const SizedBox(height: 24),
                  Icon(
                    Icons.block_rounded,
                    size: 72,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'دسترسی آژانس تعلیق شده است',
                    style: Theme.of(context).textTheme.headlineSmall,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'برای پیگیری با مدیر سامانه تماس بگیرید. تا فعال‌شدن آژانس، اطلاعات عملیاتی نمایش داده نمی‌شود.',
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 24),
                  OutlinedButton.icon(
                    onPressed: () =>
                        ref.read(authControllerProvider.notifier).logout(),
                    icon: const Icon(Icons.logout_rounded),
                    label: const Text('خروج'),
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
