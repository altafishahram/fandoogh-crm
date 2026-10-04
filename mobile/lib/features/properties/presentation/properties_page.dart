import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/localization/persian_number.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
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
  String? _city;
  RegionSelection _region = const RegionSelection();
  String? _district;
  String? _areaMin;
  String? _areaMax;
  String? _priceMin;
  String? _priceMax;
  String? _bedroomsMin;
  Map<String, Object?> _featureFilters = const <String, Object?>{};
  String _order = 'newest';
  bool _visualCards = false;

  static const _cardViewKey = 'melkban_property_card_view';

  @override
  void initState() {
    super.initState();
    _region = agencyRegion(ref);
    if (_region.provinceId != null) Future<void>.microtask(_apply);
    Future<void>.microtask(_restoreCardView);
  }

  Future<void> _restoreCardView() async {
    final value = await ref.read(secureStorageProvider).read(key: _cardViewKey);
    if (!mounted) return;
    setState(() => _visualCards = value == 'visual');
  }

  Future<void> _toggleCardView() async {
    final next = !_visualCards;
    setState(() => _visualCards = next);
    await ref
        .read(secureStorageProvider)
        .write(key: _cardViewKey, value: next ? 'visual' : 'classic');
  }

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
    final loadedCount = value.asData?.value.items.length ?? 0;
    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 14),
              child: _pageHeader(
                context,
                loadedCount: loadedCount,
                onCreate: () async {
                  final created = await context.push<bool>('/properties/new');
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
                        hintText: 'نام، محله یا کد ملک',
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
                  ),
                  const SizedBox(width: 8),
                  _filterButton(context),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
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
              padding: const EdgeInsets.fromLTRB(16, 2, 16, 8),
              child: _listToolbar(context),
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
                  visualCards: _visualCards,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pageHeader(
    BuildContext context, {
    required int loadedCount,
    required VoidCallback onCreate,
  }) {
    final theme = Theme.of(context);
    final colors = theme.colorScheme;
    final subtitle = loadedCount == 0
        ? 'ملک‌های ثبت‌شده در فایل شما'
        : '${formatPersianDigits(loadedCount)} ملک در این فهرست';
    return Row(
      children: <Widget>[
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'املاک',
                style: theme.textTheme.headlineSmall?.copyWith(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w900,
                  height: 1.1,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                subtitle,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: colors.onSurfaceVariant,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
        Tooltip(
          message: 'ملک جدید',
          child: Semantics(
            button: true,
            label: 'ثبت ملک جدید',
            child: Material(
              color: colors.primary,
              borderRadius: BorderRadius.circular(16),
              child: InkWell(
                onTap: onCreate,
                borderRadius: BorderRadius.circular(16),
                child: const SizedBox.square(
                  dimension: 48,
                  child: Icon(Icons.add_rounded, color: Colors.white, size: 26),
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

  Widget _listToolbar(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Expanded(
          child: Text(
            'فایل‌های ملک',
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
        const SizedBox(width: 2),
        Tooltip(
          message: _visualCards ? 'نمایش کارت کلاسیک' : 'نمایش کارت تصویری',
          child: IconButton(
            onPressed: _toggleCardView,
            style: IconButton.styleFrom(
              minimumSize: const Size.square(44),
              maximumSize: const Size.square(44),
              backgroundColor: colors.surface,
              foregroundColor: colors.primary,
              side: BorderSide(color: colors.outlineVariant),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            icon: Icon(
              _visualCards
                  ? Icons.view_agenda_outlined
                  : Icons.grid_view_rounded,
            ),
          ),
        ),
      ],
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

  Future<void> _apply() {
    final features = Map<String, Object?>.from(_featureFilters);
    if (_transaction == 'sale') {
      features.remove('deposit_min');
      features.remove('deposit_max');
      features.remove('rent_min');
      features.remove('rent_max');
    } else {
      features.remove('sale_price_min');
      features.remove('sale_price_max');
    }
    return ref
        .read(propertiesProvider.notifier)
        .applyFilters(
          query: _search.text,
          status: _status,
          type: _type,
          transaction: _transaction,
          district: _district,
          areaMin: _areaMin,
          areaMax: _areaMax,
          priceMin: _transaction == 'sale' ? _priceMin : null,
          priceMax: _transaction == 'sale' ? _priceMax : null,
          bedroomsMin: _bedroomsMin,
          features: <String, Object?>{
            'city': _city,
            ..._region.toJson(),
            if (_transaction == 'sale') ...<String, Object?>{
              'sale_price_min': _priceMin,
              'sale_price_max': _priceMax,
            },
            ...features,
          },
          order: _order,
        );
  }

  Future<void> _showFilters() async {
    var status = _status;
    var type = _type;
    var transaction = _transaction;
    var region = _region;
    var step = 0;
    final district = TextEditingController(text: _district);
    final city = TextEditingController(text: _city);
    final areaMin = TextEditingController(text: _areaMin);
    final areaMax = TextEditingController(text: _areaMax);
    final salePriceMin = TextEditingController(text: _priceMin);
    final salePriceMax = TextEditingController(text: _priceMax);
    final depositMin = TextEditingController(
      text: _featureFilters['deposit_min']?.toString(),
    );
    final depositMax = TextEditingController(
      text: _featureFilters['deposit_max']?.toString(),
    );
    final rentMin = TextEditingController(
      text: _featureFilters['rent_min']?.toString(),
    );
    final rentMax = TextEditingController(
      text: _featureFilters['rent_max']?.toString(),
    );
    final bedroomsMin = TextEditingController(text: _bedroomsMin);
    final parkingMin = TextEditingController(
      text: _featureFilters['parking_min']?.toString(),
    );
    var features = Map<String, Object?>.from(_featureFilters);
    final applied = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) {
          final transactionOptions = type == 'land_old_building'
              ? const <ChoiceItem>[ChoiceItem('sale', 'فروش')]
              : transactionTypes;
          if (transaction != null &&
              !transactionOptions.any((item) => item.value == transaction)) {
            transaction = 'sale';
          }

          Widget featureSwitch(String key, String label) => SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(label),
            value: features[key] == true,
            onChanged: (value) => setSheetState(() {
              if (value) {
                features[key] = true;
              } else {
                features.remove(key);
              }
            }),
          );

          Widget optionalChoice(
            String key,
            String label,
            List<ChoiceItem> items,
          ) => ChoiceField(
            value: features[key] as String?,
            label: label,
            items: items,
            includeEmpty: true,
            onChanged: (value) => setSheetState(() {
              if (value == null) {
                features.remove(key);
              } else {
                features[key] = value;
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
                    label: 'وضعیت ملک',
                    items: propertyStatuses,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() => status = value),
                  ),
                  const SizedBox(height: 10),
                  ChoiceField(
                    value: transaction,
                    label: 'نوع معامله',
                    items: transactionOptions,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() {
                      transaction = value;
                      if (transaction != 'sale') {
                        features.remove('has_loan');
                        features.remove('is_exchangeable');
                      }
                    }),
                  ),
                  const SizedBox(height: 10),
                  ChoiceField(
                    value: type,
                    label: 'نوع ملک',
                    items: propertyTypes,
                    includeEmpty: true,
                    onChanged: (value) => setSheetState(() {
                      type = value;
                      if (type == 'land_old_building') transaction = 'sale';
                      if (!<String>{
                        'house',
                        'villa',
                        'house_villa',
                      }.contains(type)) {
                        features.remove('building_type');
                      }
                      if (type != 'industrial') {
                        features.remove('has_water');
                        features.remove('has_electricity');
                        features.remove('has_gas');
                      }
                      if (type != 'land_old_building') {
                        features.remove('can_aggregate');
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
                    decoration: const InputDecoration(labelText: 'محله'),
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
              if (transaction == 'rent') {
                return Column(
                  children: <Widget>[
                    Row(
                      children: <Widget>[
                        Expanded(
                          child: _number(depositMin, 'حداقل ودیعه (تومان)'),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _number(depositMax, 'حداکثر ودیعه (تومان)'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: <Widget>[
                        Expanded(child: _number(rentMin, 'حداقل اجاره ماهانه')),
                        const SizedBox(width: 8),
                        Expanded(
                          child: _number(rentMax, 'حداکثر اجاره ماهانه'),
                        ),
                      ],
                    ),
                  ],
                );
              }
              return Row(
                children: <Widget>[
                  Expanded(
                    child: _number(salePriceMin, 'حداقل مبلغ فروش (تومان)'),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _number(salePriceMax, 'حداکثر مبلغ فروش (تومان)'),
                  ),
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
                featureSwitch('has_storage_room', 'انباری'),
                featureSwitch('has_elevator', 'آسانسور'),
                featureSwitch('has_balcony', 'بالکن'),
                featureSwitch('has_master_bathroom', 'سرویس مستر'),
                if (transaction == 'sale') ...<Widget>[
                  featureSwitch('has_loan', 'وام'),
                  featureSwitch('is_exchangeable', 'قابل معاوضه'),
                ],
                featureSwitch('has_pool', 'استخر'),
                featureSwitch('has_jacuzzi', 'جکوزی'),
                featureSwitch('has_sauna', 'سونا'),
                if (type == 'industrial') ...<Widget>[
                  featureSwitch('has_water', 'آب'),
                  featureSwitch('has_electricity', 'برق'),
                  featureSwitch('has_gas', 'گاز'),
                ],
                if (type == 'land_old_building')
                  featureSwitch('can_aggregate', 'قابلیت تجمیع'),
                const Divider(height: 24),
                optionalChoice(
                  'delivery_status',
                  'وضعیت تخلیه',
                  transaction == 'rent'
                      ? rentalDeliveryStatuses
                      : saleDeliveryStatuses,
                ),
                if (<String>{
                  'house',
                  'villa',
                  'house_villa',
                }.contains(type)) ...<Widget>[
                  const SizedBox(height: 10),
                  optionalChoice('building_type', 'نوع بنا', buildingTypes),
                ],
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
                            'فیلتر ملک | مرحله ${step + 1} از ۳',
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
        _type = type;
        _transaction = transaction;
        _region = region;
        _city = null;
        _region = const RegionSelection();
        _district = _nullable(district.text);
        _areaMin = _nullable(areaMin.text);
        _areaMax = _nullable(areaMax.text);
        _priceMin = transaction == 'sale' ? _nullable(salePriceMin.text) : null;
        _priceMax = transaction == 'sale' ? _nullable(salePriceMax.text) : null;
        _bedroomsMin = _nullable(bedroomsMin.text);
        if (transaction == 'rent') {
          features['deposit_min'] = _nullable(depositMin.text);
          features['deposit_max'] = _nullable(depositMax.text);
          features['rent_min'] = _nullable(rentMin.text);
          features['rent_max'] = _nullable(rentMax.text);
        } else {
          features.remove('deposit_min');
          features.remove('deposit_max');
          features.remove('rent_min');
          features.remove('rent_max');
        }
        final parking = _nullable(parkingMin.text);
        if (parking == null) {
          features.remove('parking_min');
        } else {
          features['parking_min'] = parking;
        }
        features = Map<String, Object?>.fromEntries(
          features.entries.where(
            (entry) => entry.value != null && entry.value != '',
          ),
        );
        _featureFilters = features;
      });
      await _apply();
    }
    city.dispose();
    district.dispose();
    areaMin.dispose();
    areaMax.dispose();
    salePriceMin.dispose();
    salePriceMax.dispose();
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
      _type != null ||
      _transaction != null ||
      _region.provinceId != null ||
      _city != null ||
      _district != null ||
      _areaMin != null ||
      _areaMax != null ||
      _priceMin != null ||
      _priceMax != null ||
      _bedroomsMin != null ||
      _featureFilters.isNotEmpty;

  List<Widget> get _filterChips => <Widget>[
    if (_status != null) Chip(label: Text(labelOf(propertyStatuses, _status))),
    if (_type != null) Chip(label: Text(labelOf(propertyTypes, _type))),
    if (_transaction != null)
      Chip(label: Text(labelOf(transactionTypes, _transaction))),
    if (_city != null) Chip(label: Text('شهر: $_city')),
    if (_district != null) Chip(label: Text('محله: $_district')),
    if (_areaMin != null || _areaMax != null)
      const Chip(label: Text('محدوده متراژ')),
    if (_priceMin != null || _priceMax != null)
      const Chip(label: Text('محدوده مبلغ')),
    if (_bedroomsMin != null) const Chip(label: Text('حداقل اتاق')),
    if (_featureFilters.isNotEmpty) const Chip(label: Text('ویژگی‌های ملک')),
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
      _city = null;
      _district = null;
      _areaMin = null;
      _areaMax = null;
      _priceMin = null;
      _priceMax = null;
      _bedroomsMin = null;
      _featureFilters = const <String, Object?>{};
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
    required this.visualCards,
  });
  final PagedResult<PropertyRecord> page;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onMore;
  final Map<String, String>? imageHeaders;
  final bool visualCards;

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
            display: visualCards
                ? PropertyCardDisplay.visual
                : PropertyCardDisplay.classic,
            onTap: item.id < 0
                ? null
                : () => context.push('/properties/${item.id}'),
          );
        },
      ),
    );
  }
}
