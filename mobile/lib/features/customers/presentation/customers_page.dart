import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/saved_filters/presentation/saved_filters.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CustomersPage extends ConsumerStatefulWidget {
  const CustomersPage({super.key});
  @override
  ConsumerState<CustomersPage> createState() => _CustomersPageState();
}

final class _CustomersPageState extends ConsumerState<CustomersPage> {
  final _search = TextEditingController();
  String? _status;
  String? _intent;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(customersProvider);
    final controller = ref.read(customersProvider.notifier);
    return Scaffold(
      appBar: AppBar(
        title: const Text('مشتریان من'),
        actions: <Widget>[
          SavedFiltersButton(
            module: 'customers',
            currentFilters: controller.activeFilters,
            onApply: (filters) {
              _search.text = filters['q'] as String? ?? '';
              setState(() {
                _status = filters['status'] as String?;
                _intent = filters['intent'] as String?;
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
        heroTag: 'new-customer',
        onPressed: () async {
          final created = await context.push<bool>('/customers/new');
          if (created == true) controller.refresh();
        },
        icon: const Icon(Icons.person_add_alt),
        label: const Text('مشتری جدید'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: SearchBar(
              controller: _search,
              hintText: 'نام، موبایل یا ایمیل…',
              leading: const Icon(Icons.search_rounded),
              onSubmitted: (query) => controller.applyFilters(
                query: query,
                status: _status,
                intent: _intent,
              ),
            ),
          ),
          if (_status != null || _intent != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: <Widget>[
                  if (_status != null)
                    Chip(label: Text(labelOf(customerStatuses, _status))),
                  if (_intent != null) ...<Widget>[
                    const SizedBox(width: 6),
                    Chip(label: Text(labelOf(customerIntents, _intent))),
                  ],
                ],
              ),
            ),
          Expanded(
            child: value.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) =>
                  ErrorState(error: error, onRetry: controller.refresh),
              data: (page) => _CustomerList(
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
    var intent = _intent;
    final applied = await showModalBottomSheet<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'فیلتر مشتریان',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 20),
                ChoiceField(
                  value: status,
                  label: 'وضعیت',
                  items: customerStatuses,
                  onChanged: (v) => setSheetState(() => status = v),
                ),
                const SizedBox(height: 12),
                ChoiceField(
                  value: intent,
                  label: 'قصد معامله',
                  items: customerIntents,
                  onChanged: (v) => setSheetState(() => intent = v),
                ),
                const SizedBox(height: 20),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          status = null;
                          intent = null;
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
      _intent = intent;
    });
    await ref
        .read(customersProvider.notifier)
        .applyFilters(query: _search.text, status: status, intent: intent);
  }
}

final class _CustomerList extends StatelessWidget {
  const _CustomerList({
    required this.page,
    required this.onRefresh,
    required this.onMore,
  });
  final PagedResult<CustomerRecord> page;
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
                  message: 'مشتری‌ای با این شرایط پیدا نشد.',
                  icon: Icons.people_outline,
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
          final customer = page.items[index];
          return Card(
            child: ListTile(
              minVerticalPadding: 14,
              leading: CircleAvatar(
                child: Text(
                  customer.name.isEmpty ? '؟' : customer.name.characters.first,
                ),
              ),
              title: Text(customer.name),
              subtitle: Text(
                '${customer.mobile}\n${labelOf(customerIntents, customer.intent)} • ${labelOf(customerStatuses, customer.status)}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: () => context.push('/customers/${customer.id}'),
            ),
          );
        },
      ),
    );
  }
}
