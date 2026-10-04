import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:fandoogh_crm/core/localization/persian_number.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/phone/phone_launcher.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/related_match_badge.dart';
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
  RegionSelection _region = const RegionSelection();
  String? _district;
  String? _areaMin;
  String? _areaMax;
  String? _budgetMin;
  String? _budgetMax;
  Map<String, Object?> _requirementFilters = const <String, Object?>{};
  String _order = 'newest';

  @override
  void initState() {
    super.initState();
    _region = agencyRegion(ref);
    if (_region.provinceId != null) Future<void>.microtask(_apply);
  }

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
    final customers = value.asData?.value.items ?? const <CustomerRecord>[];
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 14),
              child: _pageHeader(
                context,
                onCreate: () async {
                  final created = await context.push<bool>('/customers/new');
                  if (created == true) controller.refresh();
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
              child: Row(
                children: <Widget>[
                  Expanded(
                    child: SizedBox(
                      height: 48,
                      child: SearchBar(
                        controller: _search,
                        hintText: 'شماره یا نام مشتری',
                        leading: const Icon(Icons.search_rounded),
                        trailing: <Widget>[
                          if (_search.text.isNotEmpty)
                            IconButton(
                              tooltip: 'پاک‌کردن',
                              onPressed: () {
                                setState(_search.clear);
                                _apply();
                              },
                              icon: const Icon(Icons.close_rounded),
                            ),
                        ],
                        onChanged: (_) => setState(() {}),
                        onSubmitted: (_) => _apply(),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  _filterButton(context),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 0, 10, 14),
              child: _summaryCards(customers),
            ),
            if (_hasFilters)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
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
                    if (_district != null)
                      Chip(label: Text('محله: $_district')),
                    if (_areaMin != null || _areaMax != null)
                      const Chip(label: Text('محدوده متراژ')),
                    if (_budgetMin != null || _budgetMax != null)
                      const Chip(label: Text('محدوده بودجه')),
                    if (_requirementFilters.isNotEmpty)
                      const Chip(label: Text('نیازمندی‌ها')),
                    ActionChip(
                      avatar: const Icon(
                        Icons.filter_alt_off_outlined,
                        size: 18,
                      ),
                      label: const Text('حذف فیلترها'),
                      onPressed: _clearFilters,
                    ),
                  ],
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: _listToolbar(context),
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
      ),
    );
  }

  Widget _pageHeader(BuildContext context, {required VoidCallback onCreate}) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'مشتری‌ها',
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w900,
                  height: 1.1,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'تازه‌ها و پیگیری‌ها در یک نگاه',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        Tooltip(
          message: 'مشتری جدید',
          child: Semantics(
            button: true,
            label: 'ثبت مشتری جدید',
            child: Material(
              color: colors.primary,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                onTap: onCreate,
                borderRadius: BorderRadius.circular(16),
                child: const SizedBox.square(
                  dimension: 48,
                  child: Icon(
                    Icons.person_add_alt_1_rounded,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _filterButton(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Tooltip(
      message: 'فیلترها',
      child: IconButton(
        onPressed: _showFilters,
        style: IconButton.styleFrom(
          minimumSize: const Size.square(48),
          maximumSize: const Size.square(48),
          backgroundColor: colors.surface,
          foregroundColor: colors.onSurfaceVariant,
          side: BorderSide(color: colors.outlineVariant),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
        icon: Badge(
          isLabelVisible: _hasFilters,
          child: const Icon(Icons.tune_rounded),
        ),
      ),
    );
  }

  Widget _summaryCards(List<CustomerRecord> customers) {
    return Row(
      children: <Widget>[
        Expanded(
          child: _summaryCard(
            value: _countStatus(customers, 'active'),
            label: 'فعال',
            color: const Color(0xFFBFEFE8),
            foreground: const Color(0xFF115E59),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _summaryCard(
            value: _countStatus(customers, 'finalized'),
            label: 'نهایی‌شده',
            color: const Color(0xFFE8DEFF),
            foreground: const Color(0xFF7557B7),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _summaryCard(
            value: _countStatus(customers, 'withdrawn'),
            label: 'انصراف‌داده',
            color: const Color(0xFFFFEFD4),
            foreground: const Color(0xFFB45309),
          ),
        ),
      ],
    );
  }

  Widget _summaryCard({
    required int value,
    required String label,
    required Color color,
    required Color foreground,
  }) => Container(
    constraints: const BoxConstraints(minHeight: 76),
    padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
    decoration: BoxDecoration(
      color: color,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: foreground.withValues(alpha: .14)),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          formatPersianDigits(value),
          style: TextStyle(
            color: foreground,
            fontSize: 18,
            fontWeight: FontWeight.w900,
            height: 1,
          ),
        ),
        const SizedBox(height: 5),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: foreground,
            fontSize: 11,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );

  Widget _listToolbar(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            'مشتری‌های اخیر',
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
          ),
        ),
        PopupMenuButton<String>(
          tooltip: 'مرتب‌سازی',
          initialValue: _order,
          onSelected: _setOrder,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: <Widget>[
                Text(
                  _order == 'newest' ? 'جدیدترین' : 'قدیمی‌ترین',
                  style: TextStyle(
                    color: colors.onSurfaceVariant,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 4),
                Icon(
                  Icons.swap_vert_rounded,
                  size: 19,
                  color: colors.onSurfaceVariant,
                ),
              ],
            ),
          ),
          itemBuilder: (_) => const <PopupMenuEntry<String>>[
            PopupMenuItem(value: 'newest', child: Text('جدیدترین')),
            PopupMenuItem(value: 'oldest', child: Text('قدیمی‌ترین')),
          ],
        ),
      ],
    );
  }

  static int _countStatus(List<CustomerRecord> customers, String status) =>
      customers.where((customer) => customer.status == status).length;

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
            ..._region.toJson(),
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
    var region = _region;
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
                          'deed_type',
                          'has_loan',
                          'is_exchangeable',
                          'has_pool',
                          'has_jacuzzi',
                          'has_sauna',
                        ]) {
                          requirements.remove(key);
                        }
                        if (propertyType != 'apartment') {
                          requirements.remove('has_master_bathroom');
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
                      if (intent == 'rent' && propertyType != 'apartment') {
                        requirements.remove('has_master_bathroom');
                      }
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
                  RegionFields(
                    value: region,
                    onChanged: (value) => setSheetState(() {
                      region = value;
                      city.text = value.cityName ?? '';
                      district.clear();
                    }),
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
                if (intent != 'rent' || propertyType == 'apartment')
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
                if (intent != 'rent')
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
        _region = region;
        _city = null;
        _region = const RegionSelection();
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
      _region.provinceId != null ||
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
          return _CustomerCard(
            customer: item,
            onTap: item.id < 0
                ? null
                : () => context.push('/customers/${item.id}'),
          );
        },
      ),
    );
  }
}

final class _CustomerCard extends StatelessWidget {
  const _CustomerCard({required this.customer, required this.onTap});

  final CustomerRecord customer;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final type = '${customer.data['desired_property_type'] ?? ''}';
    final district = '${customer.data['desired_district'] ?? ''}'.trim();
    final need = <String>[
      labelOf(customerIntents, customer.intent),
      if (type.isNotEmpty) labelOf(propertyTypes, type),
    ].join(' · ');
    final avatarColor = customer.intent == 'rent'
        ? const Color(0xFFE8DEFF)
        : const Color(0xFFFFEFD4);
    final avatarForeground = customer.intent == 'rent'
        ? const Color(0xFF7557B7)
        : const Color(0xFFB45309);
    final statusColor = customer.status == 'active'
        ? const Color(0xFFBFEFE8)
        : const Color(0xFFE8DEFF);
    final statusForeground = customer.status == 'active'
        ? const Color(0xFF115E59)
        : const Color(0xFF7557B7);
    final area = _areaRange(
      customer.data['min_area_sqm'],
      customer.data['max_area_sqm'],
    );
    final budget = _budgetLabel();
    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 13, 14, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Row(
                children: <Widget>[
                  Container(
                    width: 48,
                    height: 48,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: avatarColor,
                      borderRadius: BorderRadius.circular(17),
                    ),
                    child: Text(
                      _initials(customer.name),
                      style: TextStyle(
                        color: avatarForeground,
                        fontSize: 16,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          customer.name.isEmpty ? 'بدون نام' : customer.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          need,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(
                                color: colors.onSurfaceVariant,
                                fontWeight: FontWeight.w600,
                              ),
                        ),
                      ],
                    ),
                  ),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      color: statusColor,
                      borderRadius: BorderRadius.circular(9),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 9,
                        vertical: 5,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: <Widget>[
                          Text(
                            labelOf(customerStatuses, customer.status),
                            style: TextStyle(
                              color: statusForeground,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(width: 4),
                          Icon(Icons.circle, size: 5, color: statusForeground),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 9),
              Wrap(
                alignment: WrapAlignment.start,
                spacing: 8,
                runSpacing: 6,
                children: <Widget>[
                  if (area != null) _infoPill('متراژ $area'),
                  if (district.isNotEmpty) _infoPill('محله $district'),
                ],
              ),
              if (budget != null) ...<Widget>[
                const SizedBox(height: 8),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: _infoPill(budget),
                ),
              ],
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 9),
                child: Divider(height: 1),
              ),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: RelatedMatchBadge(
                  scope: RelatedMatchScope.customer(customer.id),
                  summary: customer.matchSummary,
                ),
              ),
              const SizedBox(height: 8),
              Row(
                children: <Widget>[
                  _callButton(context),
                  const SizedBox(width: 8),
                  _detailsButton(),
                  const Spacer(),
                  if (customer.pendingSync)
                    Tooltip(
                      message: 'در صف همگام‌سازی',
                      child: Icon(
                        Icons.cloud_upload_outlined,
                        size: 18,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String _initials(String name) {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) return '؟';
    if (parts.length == 1) return parts.first.characters.first;
    return '${parts.first.characters.first}${parts.last.characters.first}';
  }

  Widget _infoPill(String label) => DecoratedBox(
    decoration: BoxDecoration(
      color: const Color(0xFFC4E6BC),
      borderRadius: BorderRadius.circular(8),
    ),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: const TextStyle(
          color: Color(0xFF456746),
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    ),
  );

  Widget _callButton(BuildContext context) => IconButton(
    tooltip: 'تماس با ${customer.name}',
    onPressed: customer.mobile.isEmpty
        ? null
        : () => launchPhoneCall(context, customer.mobile),
    style: IconButton.styleFrom(
      minimumSize: const Size.square(40),
      maximumSize: const Size.square(40),
      backgroundColor: const Color(0xFFBFEFE8),
      foregroundColor: const Color(0xFF115E59),
      disabledBackgroundColor: Theme.of(
        context,
      ).colorScheme.surfaceContainerLow,
      disabledForegroundColor: Theme.of(context).colorScheme.onSurfaceVariant,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
    ),
    icon: const Icon(Icons.phone_rounded, size: 21),
  );

  Widget _detailsButton() => TextButton(
    onPressed: onTap,
    style: TextButton.styleFrom(
      minimumSize: const Size(0, 40),
      padding: const EdgeInsets.symmetric(horizontal: 12),
      backgroundColor: const Color(0xFFE8DEFF),
      foregroundColor: const Color(0xFF7557B7),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
      textStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w900),
    ),
    child: const Text('مشاهده پرونده'),
  );

  String? _budgetLabel() {
    final List<String?> values = customer.intent == 'rent'
        ? <String?>[
            _value(customer.data['rental_deposit_min']),
            _value(customer.data['rental_deposit_max']),
            _value(customer.data['rental_rent_min']),
            _value(customer.data['rental_rent_max']),
          ]
        : <String?>[
            _value(customer.data['budget_min']),
            _value(customer.data['budget_max']),
          ];
    final available = values.whereType<String>().toList();
    if (available.isEmpty) return null;
    return 'مبلغ درخواستی ${available.join(' تا ')}';
  }

  static String? _value(Object? value) {
    if (value == null || '$value'.trim().isEmpty || '$value' == 'null') {
      return null;
    }
    return formatPersianDigits(value);
  }

  static String? _areaRange(Object? min, Object? max) {
    final minimum = _value(min);
    final maximum = _value(max);
    if (minimum == null && maximum == null) return null;
    if (minimum != null && maximum != null) return '$minimum تا $maximum';
    return minimum ?? maximum;
  }
}
