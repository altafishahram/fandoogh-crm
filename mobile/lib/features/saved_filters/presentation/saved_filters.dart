import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/text_input_dialog.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final savedFiltersProvider = FutureProvider<List<Map<String, dynamic>>>((ref) {
  return ApiRepository(ref.watch(apiClientProvider)).getList('/saved-filters');
});

class SavedFiltersButton extends ConsumerWidget {
  const SavedFiltersButton({
    required this.module,
    required this.currentFilters,
    required this.onApply,
    super.key,
  });
  final String module;
  final Map<String, Object?> currentFilters;
  final ValueChanged<Map<String, dynamic>> onApply;

  @override
  Widget build(BuildContext context, WidgetRef ref) => IconButton(
    tooltip: 'فیلترهای ذخیره‌شده',
    icon: const Icon(Icons.bookmarks_outlined),
    onPressed: () => showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _SavedFiltersSheet(
        module: module,
        currentFilters: currentFilters,
        onApply: onApply,
      ),
    ),
  );
}

final class _SavedFiltersSheet extends ConsumerWidget {
  const _SavedFiltersSheet({
    required this.module,
    required this.currentFilters,
    required this.onApply,
  });
  final String module;
  final Map<String, Object?> currentFilters;
  final ValueChanged<Map<String, dynamic>> onApply;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final filters = ref.watch(savedFiltersProvider);
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(
          left: 16,
          right: 16,
          top: 16,
          bottom: 16 + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            Row(
              children: <Widget>[
                Expanded(
                  child: Text(
                    'فیلترهای ذخیره‌شده',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                IconButton(
                  tooltip: 'ذخیره فیلتر فعلی',
                  onPressed: () => _save(context, ref),
                  icon: const Icon(Icons.add_rounded),
                ),
              ],
            ),
            ConstrainedBox(
              constraints: const BoxConstraints(maxHeight: 420),
              child: filters.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => ErrorState(
                  error: error,
                  onRetry: () => ref.invalidate(savedFiltersProvider),
                ),
                data: (items) {
                  final matches = items
                      .where((item) => item['module'] == module)
                      .toList();
                  if (matches.isEmpty) {
                    return const Padding(
                      padding: EdgeInsets.all(24),
                      child: Text(
                        'فیلتر ذخیره‌شده‌ای ندارید.',
                        textAlign: TextAlign.center,
                      ),
                    );
                  }
                  return ListView(
                    shrinkWrap: true,
                    children: matches
                        .map(
                          (item) => ListTile(
                            leading: Icon(
                              item['is_default'] == true
                                  ? Icons.star
                                  : Icons.bookmark_outline,
                            ),
                            title: Text('${item['name']}'),
                            subtitle: Text('${item['filters']}'),
                            onTap: () {
                              final raw = item['filters'];
                              onApply(
                                raw is Map
                                    ? Map<String, dynamic>.from(raw)
                                    : <String, dynamic>{},
                              );
                              Navigator.pop(context);
                            },
                            trailing: IconButton(
                              tooltip: 'حذف',
                              onPressed: () =>
                                  _delete(ref, (item['id'] as num).toInt()),
                              icon: const Icon(Icons.delete_outline),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _save(BuildContext context, WidgetRef ref) async {
    final name = await showDialog<String>(
      context: context,
      builder: (_) => const TextInputDialog(
        title: 'ذخیره فیلتر فعلی',
        label: 'نام فیلتر',
        confirmLabel: 'ذخیره',
      ),
    );
    if (name == null || name.isEmpty) return;
    final clean = ApiRepository.compact(currentFilters);
    await ApiRepository(ref.read(apiClientProvider)).post(
      '/saved-filters',
      <String, Object?>{
        'module': module,
        'name': name,
        'filters': clean,
        'is_default': false,
      },
    );
    ref.invalidate(savedFiltersProvider);
  }

  Future<void> _delete(WidgetRef ref, int id) async {
    await ApiRepository(
      ref.read(apiClientProvider),
    ).delete('/saved-filters/$id');
    ref.invalidate(savedFiltersProvider);
  }
}
