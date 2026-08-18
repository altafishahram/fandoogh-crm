import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
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
  late ProviderContainer _providerContainer;
  String? _status;
  String? _intent;
  String? _propertyType;
  String? _district;
  String _order = 'newest';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _providerContainer = ProviderScope.containerOf(context);
  }

  @override
  void dispose() {
    _providerContainer.invalidate(customersProvider);
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(customersProvider);
    final controller = ref.read(customersProvider.notifier);
    return Scaffold(
      appBar: AppBar(
        title: const Text('مشتریان'),
        actions: <Widget>[
          PopupMenuButton<String>(
            tooltip: 'مرتب‌سازی',
            initialValue: _order,
            onSelected: _setOrder,
            icon: const Icon(Icons.sort_rounded),
            itemBuilder: (_) => const <PopupMenuEntry<String>>[
              PopupMenuItem(value: 'newest', child: Text('جدیدترین')),
              PopupMenuItem(value: 'oldest', child: Text('قدیمی‌ترین')),
            ],
          ),
          IconButton(
            tooltip: 'فیلترها',
            onPressed: _showFilters,
            icon: Badge(
              isLabelVisible: _hasFilters,
              child: const Icon(Icons.tune_rounded),
            ),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'new-customer',
        onPressed: () async {
          final created = await context.push<bool>('/customers/new');
          if (created == true) {
            controller.refresh();
          }
        },
        icon: const Icon(Icons.person_add_alt_1),
        label: const Text('مشتری جدید'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _search,
              hintText: 'نام یا شماره همراه مشتری',
              leading: const Icon(Icons.search_rounded),
              trailing: <Widget>[
                if (_search.text.isNotEmpty)
                  IconButton(
                    tooltip: 'پاک‌کردن',
                    onPressed: _clearFilters,
                    icon: const Icon(Icons.close_rounded),
                  ),
              ],
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) => _apply(),
            ),
          ),
          if (_hasFilters)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Wrap(
                spacing: 6,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: <Widget>[
                  if (_status != null)
                    Chip(label: Text(labelOf(customerStatuses, _status))),
                  if (_intent != null)
                    Chip(label: Text(labelOf(customerIntents, _intent))),
                  if (_propertyType != null)
                    Chip(label: Text(labelOf(propertyTypes, _propertyType))),
                  if (_district != null) Chip(label: Text('محله: $_district')),
                  ActionChip(
                    avatar: const Icon(Icons.filter_alt_off_outlined, size: 18),
                    label: const Text('حذف فیلترها'),
                    onPressed: _clearFilters,
                  ),
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

  Future<void> _apply() => ref
      .read(customersProvider.notifier)
      .applyFilters(
        query: _search.text,
        status: _status,
        intent: _intent,
        propertyType: _propertyType,
        district: _district,
        order: _order,
      );

  Future<void> _showFilters() async {
    var status = _status;
    var intent = _intent;
    var propertyType = _propertyType;
    final district = TextEditingController(text: _district);
    final applied = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: Padding(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              MediaQuery.viewInsetsOf(context).bottom + 20,
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'فیلتر مشتریان',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                ChoiceField(
                  value: status,
                  label: 'وضعیت',
                  items: customerStatuses,
                  onChanged: (v) => setSheetState(() => status = v),
                ),
                const SizedBox(height: 10),
                ChoiceField(
                  value: intent,
                  label: 'نوع درخواست',
                  items: customerIntents,
                  onChanged: (v) => setSheetState(() => intent = v),
                ),
                const SizedBox(height: 10),
                ChoiceField(
                  value: propertyType,
                  label: 'نوع ملک',
                  items: propertyTypes,
                  onChanged: (v) => setSheetState(() => propertyType = v),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: district,
                  decoration: const InputDecoration(labelText: 'محلهٔ موردنظر'),
                ),
                const SizedBox(height: 18),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          status = null;
                          intent = null;
                          propertyType = null;
                          district.clear();
                          Navigator.pop(context, true);
                        },
                        child: const Text('پاک‌کردن'),
                      ),
                    ),
                    const SizedBox(width: 10),
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
    if (applied == true) {
      setState(() {
        _status = status;
        _intent = intent;
        _propertyType = propertyType;
        _district = _nullable(district.text);
      });
      await _apply();
    }
    district.dispose();
  }

  bool get _hasFilters =>
      _search.text.trim().isNotEmpty ||
      _status != null ||
      _intent != null ||
      _propertyType != null ||
      _district != null;

  Future<void> _setOrder(String value) async {
    if (_order == value) return;
    setState(() => _order = value);
    await _apply();
  }

  Future<void> _clearFilters() async {
    setState(() {
      _search.clear();
      _status = null;
      _intent = null;
      _propertyType = null;
      _district = null;
    });
    await _apply();
  }

  static String? _nullable(String value) {
    final normalized = value.trim();
    return normalized.isEmpty ? null : normalized;
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
      return const EmptyState(
        message: 'مشتری‌ای با این شرایط پیدا نشد.',
        icon: Icons.people_outline,
      );
    }
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
        itemCount: page.items.length + (page.hasMore ? 1 : 0),
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            return Center(
              child: OutlinedButton(
                onPressed: onMore,
                child: const Text('نمایش بیشتر'),
              ),
            );
          }
          final item = page.items[index];
          return Card(
            child: ListTile(
              minVerticalPadding: 14,
              leading: CircleAvatar(
                child: Text(
                  item.name.isEmpty ? '؟' : item.name.characters.first,
                ),
              ),
              title: Row(
                children: <Widget>[
                  Expanded(child: Text(item.name)),
                  if (item.pendingSync)
                    const Icon(Icons.cloud_upload_outlined, size: 18),
                ],
              ),
              subtitle: Text(
                '${item.mobile}\n${labelOf(customerIntents, item.intent)} • ${labelOf(customerStatuses, item.status)}\n${PersianDate.formatIso(item.createdAt)}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_left_rounded),
              onTap: item.id < 0
                  ? null
                  : () => context.push('/customers/${item.id}'),
            ),
          );
        },
      ),
    );
  }
}
