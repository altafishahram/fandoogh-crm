import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/offline/sync_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class SyncBanner extends ConsumerWidget {
  const SyncBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final sync = ref.watch(syncControllerProvider);
    final operations = sync.value ?? const <Map<String, dynamic>>[];
    if (!auth.isOffline && operations.isEmpty && !sync.hasError) {
      return const SizedBox.shrink();
    }
    final conflicts = operations
        .where((item) => item['status'] == 'conflict')
        .length;
    final message = auth.isOffline
        ? 'حالت آفلاین؛ ${operations.length} عملیات در صف است.'
        : conflicts > 0
        ? '$conflicts عملیات دارای تداخل است.'
        : '${operations.length} عملیات منتظر همگام‌سازی است.';
    return Material(
      color: conflicts > 0
          ? Theme.of(context).colorScheme.errorContainer
          : Theme.of(context).colorScheme.secondaryContainer,
      child: SafeArea(
        top: false,
        child: Row(
          children: <Widget>[
            const SizedBox(width: 12),
            Icon(auth.isOffline ? Icons.cloud_off_outlined : Icons.sync),
            const SizedBox(width: 8),
            Expanded(
              child: Text(message, style: const TextStyle(fontSize: 12)),
            ),
            TextButton(
              onPressed: sync.isLoading
                  ? null
                  : ref.read(syncControllerProvider.notifier).synchronize,
              child: Text(sync.isLoading ? 'در حال ارسال…' : 'همگام‌سازی'),
            ),
          ],
        ),
      ),
    );
  }
}
