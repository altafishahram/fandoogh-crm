import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:flutter/material.dart';

final class ErrorState extends StatelessWidget {
  const ErrorState({required this.error, required this.onRetry, super.key});

  final Object error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final failure = apiFailureFrom(error);
    final message = switch (failure.code) {
      'FORBIDDEN' => 'اجازهٔ انجام این عملیات را ندارید.',
      'RESOURCE_NOT_FOUND' => 'اطلاعات درخواستی پیدا نشد.',
      'STALE_RECORD' =>
        'این رکورد جای دیگری تغییر کرده است؛ دوباره بارگذاری کنید.',
      'VALIDATION_FAILED' => 'اطلاعات واردشده معتبر نیست.',
      'NETWORK_UNAVAILABLE' => 'ارتباط با سرور برقرار نشد.',
      'NETWORK_TIMEOUT' => 'زمان انتظار پاسخ سرور تمام شد.',
      _ => failure.message,
    };
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(
              Icons.error_outline,
              size: 56,
              color: Theme.of(context).colorScheme.error,
            ),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (failure.requestId != null) ...<Widget>[
              const SizedBox(height: 6),
              Text(
                'شناسه پیگیری: ${failure.requestId}',
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('تلاش دوباره'),
            ),
          ],
        ),
      ),
    );
  }
}

final class EmptyState extends StatelessWidget {
  const EmptyState({
    required this.message,
    this.icon = Icons.inbox_outlined,
    super.key,
  });
  final String message;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 56, color: Theme.of(context).colorScheme.outline),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
        ],
      ),
    ),
  );
}
