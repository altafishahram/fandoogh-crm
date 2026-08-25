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
  String? _city;
  String? _district;
  String? _areaMin;
  String? _areaMax;
  String? _budgetMin;
  String? _budgetMax;
  Map<String, Object?> _requirementFilters = const <String, Object?>{};
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
                  if (_city != null) Chip(label: Text('شهر: $_city')),
                  if (_district != null) Chip(label: Text('محله: $_district')),
                  if (_areaMin != null || _areaMax != null)
                    const Chip(label: Text('محدوده متراژ')),
                  if (_budgetMin != null || _budgetMax != null)
                    const Chip(label: Text('محدوده بودجه')),
                  if (_requirementFilters.isNotEmpty)
                    const Chip(label: Text('نیازمندی‌ها')),
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

  Future<void> _apply() {
    final requirements = Map<String, Object?>.from(_requirementFilters);
    if (_intent == 'rent') {
      requirements.remove('budget_min');
      requirements.remove('budget_max');
    } else {
      requirements.remove('deposit_min');
      requirements.remove('deposit_max');
      requirements.remove('rent_min');
      requirements.remove('rent_max');
    }
    return ref
        .read(customersProvider.notifier)
        .applyFilters(
          query: _search.text,
          status: _status,
          intent: _intent,
          propertyType: _propertyType,
          district: _district,
          requirements: <String, Object?>{
            'city': _city,
            'area_min': _areaMin,
            'area_max': _areaMax,
            ...requirements,
          },
          order: _order,
        );
  }

  Future<void> _showFilters() async {
    var status = _status;
    var intent = _intent;
    var propertyType = _propertyType;
    var step = 0;
    final city = TextEditingController(text: _city);
    final district = TextEditingController(text: _district);
    final areaMin = TextEditingController(text: _areaMin);
    final areaMax = TextEditingController(text: _areaMax);
    final budgetMin = TextEditingController(text: _budgetMin);
    final budgetMax = TextEditingController(text: _budgetMax);
    final depositMin = TextEditingController(
      text: _requirementFilters['deposit_min']?.toString(),
    );
    final depositMax = TextEditingController(
      text: _requirementFilters['deposit_max']?.toString(),
    );
    final rentMin = TextEditingController(
      text: _requirementFilters['rent_min']?.toString(),
    );
    final rentMax = TextEditingController(
      text: _requirementFilters['rent_max']?.toString(),
    );
    final bedroomsMin = TextEditingController(
      text: _requirementFilters['bedrooms_min']?.toString(),
    );
    final parkingMin = TextEditingController(
      text: _requirementFilters['parking_min']?.toString(),
    );
    var requirements = Map<String, Object?>.from(_requirementFilters);
    final applied = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final intentOptions = propertyType == 'land_old_building'
              ? const <ChoiceItem>[ChoiceItem('buy', 'خرید')]
              : customerIntents;
          if (intent != null &&
              !intentOptions.any((item) => item.value == intent)) {
            intent = 'buy';
          }

          Widget requirementSwitch(String key, String label) => SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(label),
            value: requirements[key] == true,
            onChanged: (value) => setSheetState(() {
              if (value) {
                requirements[key] = true;
              } else {
                requirements.remove(key);
              }
            }),
          );

          Widget optionalChoice(
            String key,
            String label,
            List<ChoiceItem> items,
          ) => ChoiceField(
            value: requirements[key] as String?,
            label: label,
            items: items,
            includeEmpty: true,
            onChanged: (value) => setSheetState(() {
              if (value == null) {
                requirements.remove(key);
              } else {
                requirements[key] = value;
              }
            }),
          );

          Widget stepBody() {
            if (step == 0) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: <Widget>[
                  ChoiceField(
                    value: status,
                    label: 'وضعیت مشتری',
                    items: customerStatuses,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() => status = value),
                  ),
                  const SizedBox(height: 10),
                  ChoiceField(
                    value: intent,
                    label: 'نوع درخواست',
                    items: intentOptions,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() {
                      intent = value;
                      if (intent == 'rent') {
                        for (final key in <String>[
                          'has_loan',
                          'is_exchangeable',
                          'has_pool',
                          'has_jacuzzi',
                          'has_sauna',
                        ]) {
                          requirements.remove(key);
                        }
                      } else {
                        for (final key in <String>[
                          'has_elevator',
                          'has_balcony',
                          'owner_resides',
                          'accepts_rent_conversion',
                        ]) {
                          requirements.remove(key);
                        }
                      }
                    }),
                  ),
                  const SizedBox(height: 10),
                  ChoiceField(
                    value: propertyType,
                    label: 'نوع ملک موردنظر',
                    items: propertyTypes,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() {
                      propertyType = value;
                      if (propertyType == 'land_old_building') intent = 'buy';
                      if (!<String>{
                        'house',
                        'villa',
                        'house_villa',
                      }.contains(propertyType)) {
                        requirements.remove('building_type');
                      }
                      if (propertyType != 'industrial') {
                        requirements.remove('has_water');
                        requirements.remove('has_electricity');
                        requirements.remove('has_gas');
                      }
                    }),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: city,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'شهر موردنظر'),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: district,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'محلهٔ موردنظر',
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: <Widget>[
                      Expanded(child: _number(areaMin, 'حداقل متراژ')),
                      const SizedBox(width: 8),
                      Expanded(child: _number(areaMax, 'حداکثر متراژ')),
                    ],
                  ),
                ],
              );
            }
            if (step == 1) {
              if (intent == 'rent') {
                return Column(
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(child: _number(depositMin, 'حداقل ودیعه')),
                        const SizedBox(width: 8),
                        Expanded(child: _number(depositMax, 'حداکثر ودیعه')),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: <Widget>[
                        Expanded(child: _number(rentMin, 'حداقل اجاره')),
                        const SizedBox(width: 8),
                        Expanded(child: _number(rentMax, 'حداکثر اجاره')),
                      ],
                    ),
                    const SizedBox(height: 8),
                    requirementSwitch(
                      'accepts_rent_conversion',
                      'امکان تبدیل ودیعه و اجاره',
                    ),
                  ],
                );
              }
              return Row(
                children: <Widget>[
                  Expanded(child: _number(budgetMin, 'حداقل بودجه')),
                  const SizedBox(width: 8),
                  Expanded(child: _number(budgetMax, 'حداکثر بودجه')),
                ],
              );
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Row(
                  children: <Widget>[
                    Expanded(child: _number(bedroomsMin, 'حداقل تعداد اتاق')),
                    const SizedBox(width: 8),
                    Expanded(child: _number(parkingMin, 'حداقل پارکینگ')),
                  ],
                ),
                const SizedBox(height: 10),
                if (intent == 'rent') ...<Widget>[
                  requirementSwitch('has_parking', 'پارکینگ'),
                  requirementSwitch('has_storage_room', 'انباری'),
                  requirementSwitch('has_elevator', 'آسانسور'),
                  requirementSwitch('has_balcony', 'بالکن'),
                  requirementSwitch(
                    'owner_resides',
                    'مالک در ساختمان سکونت دارد',
                  ),
                ] else ...<Widget>[
                  requirementSwitch('has_parking', 'پارکینگ'),
                  requirementSwitch('has_storage_room', 'انباری'),
                  requirementSwitch('has_loan', 'وام'),
                  requirementSwitch('is_exchangeable', 'قابل معاوضه'),
                  requirementSwitch('has_pool', 'استخر'),
                  requirementSwitch('has_jacuzzi', 'جکوزی'),
                  requirementSwitch('has_sauna', 'سونا'),
                ],
                requirementSwitch('has_master_bathroom', 'سرویس مستر'),
                if (propertyType == 'industrial') ...<Widget>[
                  requirementSwitch('has_water', 'آب'),
                  requirementSwitch('has_electricity', 'برق'),
                  requirementSwitch('has_gas', 'گاز'),
                ],
                const Divider(height: 24),
                if (<String>{
                  'house',
                  'villa',
                  'house_villa',
                }.contains(propertyType))
                  optionalChoice('building_type', 'نوع بنا', buildingTypes),
                const SizedBox(height: 10),
                optionalChoice('deed_type', 'نوع سند', deedTypes),
                const SizedBox(height: 10),
                optionalChoice('cabinet_type', 'نوع کابینت', cabinetTypes),
                const SizedBox(height: 10),
                optionalChoice('heating_type', 'نوع گرمایش', heatingTypes),
                const SizedBox(height: 10),
                optionalChoice('cooling_type', 'نوع سرمایش', coolingTypes),
                const SizedBox(height: 10),
                optionalChoice('flooring_type', 'نوع کف‌پوش', flooringTypes),
                const SizedBox(height: 10),
                optionalChoice(
                  'renovation_status',
                  'وضعیت بازسازی',
                  renovationStatuses,
                ),
                const SizedBox(height: 10),
                optionalChoice(
                  'building_orientation',
                  'جهت ساختمان',
                  buildingOrientations,
                ),
              ],
            );
          }

          Widget actionBar() => Row(
            children: <Widget>[
              if (step > 0)
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => setSheetState(() => step--),
                    child: const Text('مرحله قبل'),
                  ),
                )
              else
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => Navigator.pop(sheetContext),
                    child: const Text('انصراف'),
                  ),
                ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: () {
                    if (step < 2) {
                      setSheetState(() => step++);
                    } else {
                      Navigator.pop(sheetContext, 'apply');
                    }
                  },
                  child: Tooltip(
                    message: step < 2 ? 'رفتن به مرحله بعد' : 'اعمال فیلترها',
                    child: Text(step < 2 ? 'مرحله بعد' : 'اعمال فیلتر'),
                  ),
                ),
              ),
            ],
          );

          return SafeArea(
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.sizeOf(context).height * .86,
              ),
              child: Padding(
                padding: EdgeInsets.fromLTRB(
                  20,
                  12,
                  20,
                  MediaQuery.viewInsetsOf(context).bottom + 16,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Center(
                      child: Container(
                        width: 38,
                        height: 4,
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.outlineVariant,
                          borderRadius: BorderRadius.circular(8),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            'فیلتر مشتری | مرحله ${step + 1} از ۳',
                            style: Theme.of(context).textTheme.titleLarge,
                          ),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(sheetContext, 'apply'),
                          child: const Text('اعمال'),
                        ),
                        TextButton(
                          onPressed: () => Navigator.pop(sheetContext, 'clear'),
                          child: const Text('پاک‌کردن همه'),
                        ),
                      ],
                    ),
                    LinearProgressIndicator(value: (step + 1) / 3),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: MediaQuery.sizeOf(context).height * .52,
                      child: SingleChildScrollView(child: stepBody()),
                    ),
                    const SizedBox(height: 12),
                    actionBar(),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
    if (applied == 'clear') {
      await _clearFilters();
    } else if (applied == 'apply') {
      setState(() {
        _status = status;
        _intent = intent;
        _propertyType = propertyType;
        _city = _nullable(city.text);
        _district = _nullable(district.text);
        _areaMin = _nullable(areaMin.text);
        _areaMax = _nullable(areaMax.text);
        _budgetMin = intent == 'buy' ? _nullable(budgetMin.text) : null;
        _budgetMax = intent == 'buy' ? _nullable(budgetMax.text) : null;
        if (intent == 'rent') {
          requirements['deposit_min'] = _nullable(depositMin.text);
          requirements['deposit_max'] = _nullable(depositMax.text);
          requirements['rent_min'] = _nullable(rentMin.text);
          requirements['rent_max'] = _nullable(rentMax.text);
        } else {
          requirements.remove('deposit_min');
          requirements.remove('deposit_max');
          requirements.remove('rent_min');
          requirements.remove('rent_max');
        }
        final bedrooms = _nullable(bedroomsMin.text);
        final parking = _nullable(parkingMin.text);
        if (bedrooms == null) {
          requirements.remove('bedrooms_min');
        } else {
          requirements['bedrooms_min'] = bedrooms;
        }
        if (parking == null) {
          requirements.remove('parking_min');
        } else {
          requirements['parking_min'] = parking;
        }
        requirements = Map<String, Object?>.fromEntries(
          requirements.entries.where(
            (entry) => entry.value != null && entry.value != '',
          ),
        );
        _requirementFilters = requirements;
      });
      await _apply();
    }
    city.dispose();
    district.dispose();
    areaMin.dispose();
    areaMax.dispose();
    budgetMin.dispose();
    budgetMax.dispose();
    depositMin.dispose();
    depositMax.dispose();
    rentMin.dispose();
    rentMax.dispose();
    bedroomsMin.dispose();
    parkingMin.dispose();
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
      _intent != null ||
      _propertyType != null ||
      _city != null ||
      _areaMin != null ||
      _areaMax != null ||
      _budgetMin != null ||
      _budgetMax != null ||
      _requirementFilters.isNotEmpty ||
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
      _city = null;
      _district = null;
      _areaMin = null;
      _areaMax = null;
      _budgetMin = null;
      _budgetMax = null;
      _requirementFilters = const <String, Object?>{};
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
