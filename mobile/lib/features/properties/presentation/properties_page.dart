import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/properties/presentation/widgets/property_card.dart';
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
  late ProviderContainer _providerContainer;
  String? _status;
  String? _type;
  String? _transaction;
  String? _district;
  String? _areaMin;
  String? _areaMax;
  String? _priceMin;
  String? _priceMax;
  String? _bedroomsMin;
  String _order = 'newest';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _providerContainer = ProviderScope.containerOf(context);
  }

  @override
  void dispose() {
    _providerContainer.invalidate(propertiesProvider);
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final value = ref.watch(propertiesProvider);
    final controller = ref.read(propertiesProvider.notifier);
    return Scaffold(
      appBar: AppBar(
        title: const Text('املاک'),
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
        heroTag: 'new-property',
        onPressed: () async {
          final created = await context.push<bool>('/properties/new');
          if (created == true) {
            controller.refresh();
          }
        },
        icon: const Icon(Icons.add_rounded),
        label: const Text('ملک جدید'),
      ),
      body: Column(
        children: <Widget>[
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
            child: SearchBar(
              controller: _search,
              hintText: 'کد، عنوان یا نام مالک',
              leading: const Icon(Icons.search_rounded),
              trailing: <Widget>[
                if (_search.text.isNotEmpty)
                  IconButton(
                    tooltip: 'پاک‌کردن',
                    onPressed: () {
                      _search.clear();
                      _apply();
                    },
                    icon: const Icon(Icons.close_rounded),
                  ),
              ],
              onChanged: (_) => setState(() {}),
              onSubmitted: (_) => _apply(),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Row(
              children: <Widget>[
                _transactionFilterButton(label: 'همه', value: null),
                const SizedBox(width: 6),
                _transactionFilterButton(label: 'فروش', value: 'sale'),
                const SizedBox(width: 6),
                _transactionFilterButton(label: 'اجاره', value: 'rent'),
              ],
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
                  ..._filterChips,
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
              data: (page) => _PropertyList(
                page: page,
                onRefresh: controller.refresh,
                onMore: controller.loadMore,
                imageHeaders: _imageHeaders,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Map<String, String>? get _imageHeaders {
    final token = ref.read(apiClientProvider).token;
    return token == null || token.isEmpty
        ? null
        : <String, String>{'Authorization': 'Bearer $token'};
  }

  Widget _transactionFilterButton({
    required String label,
    required String? value,
  }) {
    final selected = _transaction == value;
    final style = ButtonStyle(
      minimumSize: const WidgetStatePropertyAll<Size>(Size.fromHeight(46)),
      padding: const WidgetStatePropertyAll<EdgeInsets>(
        EdgeInsets.symmetric(horizontal: 8),
      ),
    );
    return Expanded(
      child: selected
          ? FilledButton(
              onPressed: () => _setTransaction(value),
              style: style,
              child: Text(label),
            )
          : OutlinedButton(
              onPressed: () => _setTransaction(value),
              style: style,
              child: Text(label),
            ),
    );
  }

  Future<void> _setTransaction(String? value) async {
    if (_transaction == value) return;
    setState(() => _transaction = value);
    await _apply();
  }

  Future<void> _apply() => ref
      .read(propertiesProvider.notifier)
      .applyFilters(
        query: _search.text,
        status: _status,
        type: _type,
        transaction: _transaction,
        district: _district,
        areaMin: _areaMin,
        areaMax: _areaMax,
        priceMin: _priceMin,
        priceMax: _priceMax,
        bedroomsMin: _bedroomsMin,
        order: _order,
      );

  Future<void> _showFilters() async {
    var status = _status;
    var type = _type;
    var transaction = _transaction;
    final district = TextEditingController(text: _district);
    final areaMin = TextEditingController(text: _areaMin);
    final areaMax = TextEditingController(text: _areaMax);
    final priceMin = TextEditingController(text: _priceMin);
    final priceMax = TextEditingController(text: _priceMax);
    final bedroomsMin = TextEditingController(text: _bedroomsMin);
    final applied = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (context) => StatefulBuilder(
        builder: (context, setSheetState) => SafeArea(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(
              20,
              20,
              20,
              MediaQuery.viewInsetsOf(context).bottom + 20,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'فیلتر املاک',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 16),
                ChoiceField(
                  value: status,
                  label: 'وضعیت',
                  items: propertyStatuses,
                  onChanged: (v) => setSheetState(() => status = v),
                ),
                const SizedBox(height: 10),
                ChoiceField(
                  value: type,
                  label: 'نوع ملک',
                  items: propertyTypes,
                  onChanged: (v) => setSheetState(() => type = v),
                ),
                const SizedBox(height: 10),
                ChoiceField(
                  value: transaction,
                  label: 'نوع معامله',
                  items: transactionTypes,
                  onChanged: (v) => setSheetState(() => transaction = v),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: district,
                  decoration: const InputDecoration(labelText: 'محله'),
                ),
                const SizedBox(height: 10),
                Row(
                  children: <Widget>[
                    Expanded(child: _number(areaMin, 'متراژ از')),
                    const SizedBox(width: 8),
                    Expanded(child: _number(areaMax, 'متراژ تا')),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: <Widget>[
                    Expanded(child: _number(priceMin, 'مبلغ از (تومان)')),
                    const SizedBox(width: 8),
                    Expanded(child: _number(priceMax, 'مبلغ تا (تومان)')),
                  ],
                ),
                const SizedBox(height: 10),
                _number(bedroomsMin, 'حداقل اتاق'),
                const SizedBox(height: 18),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () {
                          status = null;
                          type = null;
                          transaction = null;
                          district.clear();
                          areaMin.clear();
                          areaMax.clear();
                          priceMin.clear();
                          priceMax.clear();
                          bedroomsMin.clear();
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
        _type = type;
        _transaction = transaction;
        _district = _nullable(district.text);
        _areaMin = _nullable(areaMin.text);
        _areaMax = _nullable(areaMax.text);
        _priceMin = _nullable(priceMin.text);
        _priceMax = _nullable(priceMax.text);
        _bedroomsMin = _nullable(bedroomsMin.text);
      });
      await _apply();
    }
    district.dispose();
    areaMin.dispose();
    areaMax.dispose();
    priceMin.dispose();
    priceMax.dispose();
    bedroomsMin.dispose();
  }

  static TextField _number(TextEditingController controller, String label) =>
      TextField(
        controller: controller,
        keyboardType: TextInputType.number,
        decoration: InputDecoration(labelText: label),
      );

  bool get _hasFilters =>
      _search.text.trim().isNotEmpty ||
      _status != null ||
      _type != null ||
      _transaction != null ||
      _district != null ||
      _areaMin != null ||
      _areaMax != null ||
      _priceMin != null ||
      _priceMax != null ||
      _bedroomsMin != null;

  List<Widget> get _filterChips => <Widget>[
    if (_status != null) Chip(label: Text(labelOf(propertyStatuses, _status))),
    if (_type != null) Chip(label: Text(labelOf(propertyTypes, _type))),
    if (_district != null) Chip(label: Text('محله: $_district')),
    if (_areaMin != null || _areaMax != null)
      const Chip(label: Text('محدوده متراژ')),
    if (_priceMin != null || _priceMax != null)
      const Chip(label: Text('محدوده مبلغ')),
    if (_bedroomsMin != null) const Chip(label: Text('حداقل اتاق')),
  ];

  Future<void> _setOrder(String value) async {
    if (_order == value) return;
    setState(() => _order = value);
    await _apply();
  }

  Future<void> _clearFilters() async {
    setState(() {
      _search.clear();
      _status = null;
      _type = null;
      _transaction = null;
      _district = null;
      _areaMin = null;
      _areaMax = null;
      _priceMin = null;
      _priceMax = null;
      _bedroomsMin = null;
    });
    await _apply();
  }

  static String? _nullable(String value) {
    final normalized = value.trim();
    return normalized.isEmpty ? null : normalized;
  }
}

final class _PropertyList extends StatelessWidget {
  const _PropertyList({
    required this.page,
    required this.onRefresh,
    required this.onMore,
    required this.imageHeaders,
  });
  final PagedResult<PropertyRecord> page;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onMore;
  final Map<String, String>? imageHeaders;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return const EmptyState(
        message: 'ملکی با این شرایط پیدا نشد.',
        icon: Icons.apartment_outlined,
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
          return PropertyCard.fromRecord(
            item,
            imageHeaders: imageHeaders,
            onTap: item.id < 0
                ? null
                : () => context.push('/properties/${item.id}'),
          );
        },
      ),
    );
  }
}
