import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/widgets/section_card.dart';
import 'package:fandoogh_crm/features/properties/presentation/widgets/property_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final dashboardProvider = FutureProvider<Map<String, dynamic>>((ref) {
  return ApiRepository(ref.watch(apiClientProvider)).getOne('/dashboard');
});

final class DashboardPage extends ConsumerWidget {
  const DashboardPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dashboard = ref.watch(dashboardProvider);
    final auth = ref.watch(authControllerProvider);
    return Scaffold(
      appBar: AppBar(
        title: Text('سلام ${auth.displayName}'),
        actions: <Widget>[
          IconButton(
            tooltip: 'جست‌وجوی سراسری',
            onPressed: () => context.push('/search'),
            icon: const Icon(Icons.search_rounded),
          ),
        ],
      ),
      body: dashboard.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, _) => ErrorState(
          error: error,
          onRetry: () => ref.invalidate(dashboardProvider),
        ),
        data: (data) => RefreshIndicator(
          onRefresh: () async => ref.invalidate(dashboardProvider),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: <Widget>[
              _StatusSummary(data: data, isManager: auth.isManager),
              const SizedBox(height: 12),
              SectionCard(
                title: 'دسترسی سریع',
                child: Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: <Widget>[
                    if (auth.can('properties.create'))
                      FilledButton.tonalIcon(
                        onPressed: () => context.push('/properties/new'),
                        icon: const Icon(Icons.add_home_work_outlined),
                        label: const Text('ملک جدید'),
                      ),
                    if (auth.can('customers.create'))
                      FilledButton.tonalIcon(
                        onPressed: () => context.push('/customers/new'),
                        icon: const Icon(Icons.person_add_alt),
                        label: const Text('مشتری جدید'),
                      ),
                    FilledButton.tonalIcon(
                      onPressed: () => context.push('/search'),
                      icon: const Icon(Icons.manage_search_rounded),
                      label: const Text('جست‌وجو'),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              _RecentProperties(items: data['recent_properties']),
              const SizedBox(height: 12),
              _RecentNotes(items: data['recent_notes']),
            ],
          ),
        ),
      ),
    );
  }
}

final class _StatusSummary extends StatelessWidget {
  const _StatusSummary({required this.data, required this.isManager});
  final Map<String, dynamic> data;
  final bool isManager;

  @override
  Widget build(BuildContext context) {
    final raw = data['properties_by_status'];
    final statuses = raw is Map
        ? Map<String, dynamic>.from(raw)
        : <String, dynamic>{};
    return SectionCard(
      title: isManager ? 'وضعیت آژانس' : 'وضعیت کار من',
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: <Widget>[
          ...statuses.entries.map(
            (entry) => Chip(
              avatar: CircleAvatar(child: Text('${entry.value}')),
              label: Text(labelOf(propertyStatuses, entry.key)),
            ),
          ),
          Chip(
            avatar: CircleAvatar(
              child: Text('${data['active_customers'] ?? 0}'),
            ),
            label: const Text('مشتری فعال'),
          ),
        ],
      ),
    );
  }
}

final class _RecentProperties extends ConsumerWidget {
  const _RecentProperties({required this.items});
  final Object? items;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final rawItems = items;
    final values = rawItems is List
        ? rawItems
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList(growable: false)
        : const <Map<String, dynamic>>[];
    final token = ref.read(apiClientProvider).token;
    final imageHeaders = token == null || token.isEmpty
        ? null
        : <String, String>{'Authorization': 'Bearer $token'};
    return SectionCard(
      title: 'املاک اخیر',
      child: values.isEmpty
          ? const Text('هنوز ملکی ثبت نشده است.')
          : Column(
              children: <Widget>[
                for (var index = 0; index < values.length; index++) ...<Widget>[
                  PropertyCard.fromMap(
                    values[index],
                    imageHeaders: imageHeaders,
                    onTap: () =>
                        context.push('/properties/${values[index]['id']}'),
                  ),
                  if (index < values.length - 1) const SizedBox(height: 8),
                ],
              ],
            ),
    );
  }
}

final class _RecentNotes extends StatelessWidget {
  const _RecentNotes({required this.items});
  final Object? items;

  @override
  Widget build(BuildContext context) {
    final rawItems = items;
    final values = rawItems is List
        ? rawItems.whereType<Map>().toList()
        : <Map>[];
    return SectionCard(
      title: 'یادداشت‌های اخیر',
      child: values.isEmpty
          ? const Text('یادداشت تازه‌ای وجود ندارد.')
          : Column(
              children: values
                  .map(
                    (item) => ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.note_alt_outlined),
                      title: Text(
                        '${item['body'] ?? ''}',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                      onTap: () =>
                          context.push('/properties/${item['property_id']}'),
                    ),
                  )
                  .toList(growable: false),
            ),
    );
  }
}
