import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/saved_filters/presentation/saved_filters.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class PropertiesPage extends ConsumerStatefulWidget {
  const PropertiesPage({super.key});

  @override
  ConsumerState<PropertiesPage> createState() => _PropertiesPageState();
}

final class _PropertiesPageState extends ConsumerState<PropertiesPage> {
  final _search = TextEditingController();
  String? _status;
  String? _type;
  String? _transaction;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(propertiesProvider);
    final controller = ref.read(propertiesProvider.notifier);
    return Scaffold(
      appBar: AppBar(
        title: const Text('املاک من'),
        actions: <Widget>[
          SavedFiltersButton(
            module: 'properties',
            currentFilters: controller.activeFilters,
            onApply: (filters) {
              _search.text = filters['q'] as String? ?? '';
              setState(() {
                _status = filters['status'] as String?;
                _type = filters['property_type'] as String?;
                _transaction = filters['transaction_type'] as String?;
              });
              controller.applySaved(filters);
            },
          ),
          IconButton(
            tooltip: 'فیلترها',
            onPressed: _showFilters,
            icon: const Icon(Icons.tune_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'new-property',
        onPressed: () async {
          final created = await context.push<bool>('/properties/new');
          if (created == true) controller.refresh();
        },
        icon: const Icon(Icons.add_rounded),
        label: const Text('ملک جدید'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: SearchBar(
              controller: _search,
              hintText: 'کد یا عنوان ملک…',
              leading: const Icon(Icons.search_rounded),
              trailing: <Widget>[
                if (_search.text.isNotEmpty)
                  IconButton(
                    tooltip: 'پاک‌کردن',
                    onPressed: () {
                      _search.clear();
                      controller.applyFilters(
                        status: _status,
                        type: _type,
                        transaction: _transaction,
                      );
                      setState(() {});
                    },
                    icon: const Icon(Icons.clear_rounded),
                  ),
              ],
              onSubmitted: (query) => controller.applyFilters(
                query: query,
                status: _status,
                type: _type,
                transaction: _transaction,
              ),
            ),
          ),
          if (_status != null || _type != null || _transaction != null)
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: <Widget>[
                  if (_status != null)
                    Chip(label: Text(labelOf(propertyStatuses, _status))),
                  if (_type != null) ...<Widget>[
                    const SizedBox(width: 6),
                    Chip(label: Text(labelOf(propertyTypes, _type))),
                  ],
                  if (_transaction != null) ...<Widget>[
                    const SizedBox(width: 6),
                    Chip(label: Text(labelOf(transactionTypes, _transaction))),
                  ],
                ],
              ),
            ),
          Expanded(
            child: value.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) =>
                  ErrorState(error: error, onRetry: controller.refresh),
              data: (page) => _PropertyList(
                page: page,
                onRefresh: controller.refresh,
                onMore: controller.loadMore,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _showFilters() async {
    var status = _status;
    var type = _type;
    var transaction = _transaction;
    final applied = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'فیلتر املاک',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 20),
                ChoiceField(
                  value: status,
                  label: 'وضعیت',
                  items: propertyStatuses,
                  onChanged: (v) => setSheetState(() => status = v),
                ),
                const SizedBox(height: 12),
                ChoiceField(
                  value: type,
                  label: 'نوع ملک',
                  items: propertyTypes,
                  onChanged: (v) => setSheetState(() => type = v),
                ),
                const SizedBox(height: 12),
                ChoiceField(
                  value: transaction,
                  label: 'نوع معامله',
                  items: transactionTypes,
                  onChanged: (v) => setSheetState(() => transaction = v),
                ),
                const SizedBox(height: 20),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          status = null;
                          type = null;
                          transaction = null;
                          Navigator.pop(context, true);
                        },
                        child: const Text('پاک‌کردن'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: () => Navigator.pop(context, true),
                        child: const Text('اعمال'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
    if (applied != true) return;
    setState(() {
      _status = status;
      _type = type;
      _transaction = transaction;
    });
    await ref
        .read(propertiesProvider.notifier)
        .applyFilters(
          query: _search.text,
          status: status,
          type: type,
          transaction: transaction,
        );
  }
}

final class _PropertyList extends StatelessWidget {
  const _PropertyList({
    required this.page,
    required this.onRefresh,
    required this.onMore,
  });
  final PagedResult<PropertyRecord> page;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onMore;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return LayoutBuilder(
        builder: (context, constraints) => RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: <Widget>[
              SizedBox(
                height: constraints.maxHeight,
                child: const EmptyState(
                  message: 'ملکی با این شرایط پیدا نشد.',
                  icon: Icons.apartment_outlined,
                ),
              ),
            ],
          ),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
        itemCount: page.items.length + (page.hasMore ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return Center(
              child: OutlinedButton.icon(
                onPressed: onMore,
                icon: const Icon(Icons.expand_more),
                label: const Text('نمایش بیشتر'),
              ),
            );
          }
          final property = page.items[index];
          return Card(
            child: ListTile(
              minVerticalPadding: 14,
              leading: const CircleAvatar(
                child: Icon(Icons.apartment_outlined),
              ),
              title: Text(property.title),
              subtitle: Text(
                '${property.code} • ${property.city}\n${labelOf(transactionTypes, property.transactionType)} • ${labelOf(propertyStatuses, property.status)}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: () => context.push('/properties/${property.id}'),
            ),
          );
        },
      ),
    );
  }
}
