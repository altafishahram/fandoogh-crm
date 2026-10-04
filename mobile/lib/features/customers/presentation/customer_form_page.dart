import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/localization/persian_number.dart';
import 'package:fandoogh_crm/core/offline/sync_controller.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CustomerFormPage extends ConsumerStatefulWidget {
  const CustomerFormPage({this.customerId, super.key});
  final int? customerId;
  @override
  ConsumerState<CustomerFormPage> createState() => _CustomerFormPageState();
}

final class _CustomerFormPageState extends ConsumerState<CustomerFormPage>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields =
      <String, TextEditingController>{};
  late final AnimationController _stepAnimation;
  int _step = 0;
  int? _previousStep;
  int _stepDirection = 1;
  String _intent = 'buy';
  String _status = 'active';
  String _propertyType = 'apartment';
  bool _acceptsConversion = true;
  final Set<String> _toiletTypes = <String>{};
  bool _masterBathroom = false;
  String? _cabinetType;
  String? _heatingType;
  String? _coolingType;
  String? _flooringType;
  String? _renovationStatus;
  String? _buildingOrientation;
  String? _deedType;
  bool _hasLoan = false;
  bool _exchangeable = false;
  bool _pool = false;
  bool _jacuzzi = false;
  bool _sauna = false;
  String? _buildingType;
  bool _hasWater = false;
  bool _hasElectricity = false;
  bool _hasGas = false;
  bool _hasElevator = false;
  bool _hasBalcony = false;
  bool _hasParking = false;
  bool _hasStorageRoom = false;
  bool _ownerResides = false;
  bool _loading = false;
  bool _saving = false;
  RegionSelection _region = const RegionSelection();
  String? _error;
  CustomerRecord? _original;
  static const _displayNumericFields = <String>{
    'area_min',
    'area_max',
    'bedrooms',
    'budget_min',
    'budget_max',
    'deposit_min',
    'deposit_max',
    'rent_min',
    'rent_max',
    'telephone_line_count',
    'land_area',
    'building_area',
    'land_area_min',
    'land_area_max',
    'building_area_min',
    'building_area_max',
  };
  static const _integerNumericFields = <String>{
    'bedrooms',
    'telephone_line_count',
  };
  bool get _editing => widget.customerId != null;
  bool get _isLand => _propertyType == 'land_old_building';
  bool get _isHouseVilla =>
      <String>{'house', 'villa', 'house_villa'}.contains(_propertyType);
  bool get _isIndustrial => _propertyType == 'industrial';
  bool get _showMasterBathroom =>
      _intent != 'rent' || _propertyType == 'apartment';
  static const _stepTitles = <String>[
    'اطلاعات تماس',
    'نوع نیاز',
    'ویژگی‌های موردنظر',
    'محدوده و بودجه',
    'توضیحات و تأیید',
  ];
  TextEditingController _c(String key) =>
      _fields.putIfAbsent(key, TextEditingController.new);

  @override
  void initState() {
    super.initState();
    if (!_editing) _region = agencyRegion(ref);
    _stepAnimation = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 260),
    );
    if (_editing) {
      Future<void>.microtask(_load);
    }
  }

  @override
  void dispose() {
    _stepAnimation.dispose();
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_editing ? 'ویرایش مشتری' : 'ثبت مشتری جدید')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : Form(
            key: _formKey,
            child: Column(
              children: <Widget>[
                _flowHeader(),
                Expanded(child: _stepPages()),
                _flowControls(),
              ],
            ),
          ),
  );

  Widget _flowHeader() => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
    child: Column(
      children: <Widget>[
        Row(
          children: <Widget>[
            for (var index = 0; index < _stepTitles.length; index++)
              Expanded(
                child: InkWell(
                  borderRadius: BorderRadius.circular(12),
                  onTap: () => _goToStep(index),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 2),
                    child: Column(
                      children: <Widget>[
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 180),
                          width: 28,
                          height: 28,
                          decoration: BoxDecoration(
                            color: index == _step
                                ? Theme.of(context).colorScheme.primary
                                : index < _step
                                ? Theme.of(
                                    context,
                                  ).colorScheme.primary.withValues(alpha: .55)
                                : Theme.of(
                                    context,
                                  ).colorScheme.surfaceContainerHighest,
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            persianDigits(index + 1),
                            style: TextStyle(
                              color: index <= _step
                                  ? Colors.white
                                  : Theme.of(context).colorScheme.onSurface,
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          _stepTitles[index],
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: index == _step
                                ? Theme.of(context).colorScheme.primary
                                : Theme.of(context).colorScheme.onSurface,
                            fontSize: 11,
                            fontWeight: index == _step
                                ? FontWeight.w800
                                : FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            minHeight: 4,
            value: (_step + 1) / _stepTitles.length,
          ),
        ),
      ],
    ),
  );

  Widget _stepPages() {
    final contents = <Widget>[
      _contactStep(),
      _needStep(),
      _featuresStep(),
      _budgetStep(),
      _finalStep(),
    ];
    final order = <int>[
      for (var index = 0; index < contents.length; index++)
        if (index != _previousStep && index != _step) index,
      _step,
    ];
    if (_previousStep != null) {
      order.insert(order.length - 1, _previousStep!);
    }

    return ClipRect(
      child: Stack(
        fit: StackFit.expand,
        children: order
            .map(
              (index) => Positioned.fill(
                key: ValueKey<String>('customer-step-container-$index'),
                child: Offstage(
                  offstage: index != _step && index != _previousStep,
                  child: IgnorePointer(
                    ignoring: index != _step,
                    child: SlideTransition(
                      position: _stepPosition(index),
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                        child: KeyedSubtree(
                          key: ValueKey<String>('customer-step-$index'),
                          child: contents[index],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            )
            .toList(growable: false),
      ),
    );
  }

  Widget _flowControls() => Padding(
    padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
    child: Row(
      children: <Widget>[
        FilledButton.icon(
          onPressed: _step == _stepTitles.length - 1
              ? (_saving ? null : _submit)
              : () => _goToStep(_step + 1),
          icon: Icon(
            _step == _stepTitles.length - 1
                ? Icons.save_outlined
                : Icons.arrow_back_rounded,
          ),
          label: Text(
            _saving
                ? 'در حال ثبت…'
                : _step == _stepTitles.length - 1
                ? 'ذخیره مشتری'
                : 'مرحله بعد',
          ),
        ),
        if (_step > 0)
          TextButton(
            onPressed: () => _goToStep(_step - 1),
            child: const Text('مرحله قبل'),
          ),
      ],
    ),
  );

  Animation<Offset> _stepPosition(int index) {
    if (_previousStep == null) {
      return const AlwaysStoppedAnimation<Offset>(Offset.zero);
    }
    final direction = _stepDirection.toDouble();
    final curve = CurvedAnimation(
      parent: _stepAnimation,
      curve: index == _step ? Curves.easeOutCubic : Curves.easeInCubic,
    );
    if (index == _step) {
      return Tween<Offset>(
        begin: Offset(direction, 0),
        end: Offset.zero,
      ).animate(curve);
    }
    if (index == _previousStep) {
      return Tween<Offset>(
        begin: Offset.zero,
        end: Offset(-direction, 0),
      ).animate(curve);
    }
    return const AlwaysStoppedAnimation<Offset>(Offset.zero);
  }

  void _goToStep(int value) {
    if (value < 0 || value >= _stepTitles.length || value == _step) {
      return;
    }
    final previous = _step;
    _stepAnimation.stop();
    setState(() {
      _previousStep = previous;
      _stepDirection = value > previous ? 1 : -1;
      _step = value;
    });
    _stepAnimation.forward(from: 0).whenCompleteOrCancel(() {
      if (mounted && _previousStep == previous) {
        setState(() => _previousStep = null);
      }
    });
  }

  Widget _contactStep() => Column(
    children: <Widget>[
      _text('full_name', 'نام کامل مشتری', required: true),
      _gap,
      _text(
        'mobile',
        'شماره همراه',
        required: true,
        keyboard: TextInputType.phone,
      ),
      _gap,
      _text('phone', 'تلفن ثابت', keyboard: TextInputType.phone),
    ],
  );

  Widget _needStep() => Column(
    children: <Widget>[
      ChoiceField(
        value: _intent,
        label: 'نوع درخواست',
        items: _isLand
            ? const <ChoiceItem>[ChoiceItem('buy', 'خرید')]
            : customerIntents,
        onChanged: _editing
            ? (_) {}
            : (v) => setState(() {
                _intent = v!;
                if (_intent == 'rent') {
                  _hasLoan = false;
                  _exchangeable = false;
                  _pool = false;
                  _jacuzzi = false;
                  _sauna = false;
                  _deedType = null;
                  if (_propertyType != 'apartment') _masterBathroom = false;
                } else {
                  _c('parking_spaces').clear();
                  _hasElevator = false;
                  _hasBalcony = false;
                  _ownerResides = false;
                }
              }),
      ),
      _gap,
      ChoiceField(
        value: _propertyType,
        label: 'نوع ملک موردنظر',
        items: propertyTypes,
        onChanged: (v) => setState(() {
          _propertyType = v!;
          if (_isLand) {
            _intent = 'buy';
            _c('parking_spaces').clear();
            _hasElevator = false;
            _hasBalcony = false;
            _ownerResides = false;
          }
          if (!_isHouseVilla) _buildingType = null;
          if (!_isIndustrial) _clearIndustrialFields();
          if (_intent == 'rent' && _propertyType != 'apartment') {
            _masterBathroom = false;
          }
        }),
      ),
      if (_editing) ...<Widget>[
        _gap,
        ChoiceField(
          value: _status,
          label: 'وضعیت مشتری',
          items: customerStatuses,
          onChanged: (v) => setState(() => _status = v!),
        ),
      ],
    ],
  );

  Widget _budgetStep() => Column(
    children: <Widget>[
      RegionFields(
        value: _region,
        requiredCity: false,
        legacyCity: _editing ? _c('city').text : null,
        onChanged: (region) => setState(() {
          _region = region;
          _c('city').text = region.cityName ?? '';
          _c('district').clear();
        }),
      ),
      _gap,
      _text('district', 'محلهٔ موردنظر'),
      _gap,
      if (!_isIndustrial)
        Row(
          children: <Widget>[
            Expanded(child: _number('area_min', 'متراژ از', required: true)),
            const SizedBox(width: 8),
            Expanded(child: _number('area_max', 'متراژ تا', required: true)),
          ],
        ),
      _gap,
      if (_intent == 'buy') ...<Widget>[
        _number('bedrooms', 'حداقل تعداد اتاق', integer: true),
        _gap,
        Row(
          children: <Widget>[
            Expanded(
              child: _number(
                'budget_min',
                'بودجه از (تومان)',
                integer: true,
                amount: true,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _number(
                'budget_max',
                'بودجه تا (تومان)',
                integer: true,
                amount: true,
              ),
            ),
          ],
        ),
      ] else ...<Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: _number(
                'deposit_min',
                'ودیعه از',
                required: true,
                integer: true,
                amount: true,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _number(
                'deposit_max',
                'ودیعه تا',
                required: true,
                integer: true,
                amount: true,
              ),
            ),
          ],
        ),
        _gap,
        Row(
          children: <Widget>[
            Expanded(
              child: _number(
                'rent_min',
                'اجاره از',
                required: true,
                integer: true,
                amount: true,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _number(
                'rent_max',
                'اجاره تا',
                required: true,
                integer: true,
                amount: true,
              ),
            ),
          ],
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('امکان تبدیل ودیعه و اجاره'),
          subtitle: const Text(
            'هر یک میلیون تومان ودیعه برابر سی هزار تومان اجاره',
          ),
          value: _acceptsConversion,
          onChanged: (v) => setState(() => _acceptsConversion = v),
        ),
      ],
    ],
  );

  Widget _featuresStep() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      if (_intent == 'rent') ...<Widget>[
        Text(
          'ویژگی‌های اجاره‌ای',
          style: Theme.of(context).textTheme.titleSmall,
        ),
        _gap,
        _number('bedrooms', 'حداقل تعداد اتاق', integer: true),
        _gap,
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('آسانسور'),
          value: _hasElevator,
          onChanged: (value) => setState(() => _hasElevator = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('بالکن'),
          value: _hasBalcony,
          onChanged: (value) => setState(() => _hasBalcony = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('مالک در ساختمان سکونت دارد'),
          value: _ownerResides,
          onChanged: (value) => setState(() => _ownerResides = value),
        ),
        const Divider(height: 28),
      ],
      SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: const Text('پارکینگ'),
        value: _hasParking,
        onChanged: (value) => setState(() => _hasParking = value),
      ),
      SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: const Text('انباری'),
        value: _hasStorageRoom,
        onChanged: (value) => setState(() => _hasStorageRoom = value),
      ),
      if (_isHouseVilla) ...<Widget>[
        ChoiceField(
          value: _buildingType,
          label: 'نوع بنا',
          items: buildingTypes,
          includeEmpty: true,
          required: true,
          onChanged: (value) => setState(() => _buildingType = value),
        ),
        _gap,
      ],
      if (_isIndustrial) ...<Widget>[
        Text('مشخصات صنعتی', style: Theme.of(context).textTheme.titleSmall),
        _gap,
        _text('structure_type', 'نوع سازه'),
        _gap,
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('آب'),
          value: _hasWater,
          onChanged: (value) => setState(() => _hasWater = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('برق'),
          value: _hasElectricity,
          onChanged: (value) => setState(() => _hasElectricity = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('گاز'),
          value: _hasGas,
          onChanged: (value) => setState(() => _hasGas = value),
        ),
        _text(
          'telephone_line_count',
          'تعداد خط تلفن',
          groupedNumber: true,
          integer: true,
        ),
        _gap,
        _number('land_area_min', 'حداقل متراژ زمین', required: true),
        _gap,
        _number('land_area_max', 'حداکثر متراژ زمین', required: true),
        _gap,
        _number('building_area_min', 'حداقل متراژ بنا', required: true),
        _gap,
        _number('building_area_max', 'حداکثر متراژ بنا', required: true),
        const Divider(height: 28),
      ],
      Text('نوع سرویس', style: Theme.of(context).textTheme.titleSmall),
      ...toiletTypes.map(
        (item) => CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(item.label),
          value: _toiletTypes.contains(item.value),
          onChanged: (selected) => setState(() {
            if (selected == true) {
              _toiletTypes.add(item.value);
            } else {
              _toiletTypes.remove(item.value);
            }
          }),
        ),
      ),
      if (_showMasterBathroom)
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('سرویس مستر'),
          value: _masterBathroom,
          onChanged: (value) => setState(() => _masterBathroom = value),
        ),
      ChoiceField(
        value: _cabinetType,
        label: 'نوع کابینت',
        items: cabinetTypes,
        includeEmpty: true,
        onChanged: (value) => setState(() => _cabinetType = value),
      ),
      _gap,
      ChoiceField(
        value: _heatingType,
        label: 'نوع گرمایش',
        items: heatingTypes,
        includeEmpty: true,
        onChanged: (value) => setState(() => _heatingType = value),
      ),
      _gap,
      ChoiceField(
        value: _coolingType,
        label: 'نوع سرمایش',
        items: coolingTypes,
        includeEmpty: true,
        onChanged: (value) => setState(() => _coolingType = value),
      ),
      _gap,
      ChoiceField(
        value: _flooringType,
        label: 'نوع کف‌پوش',
        items: flooringTypes,
        includeEmpty: true,
        onChanged: (value) => setState(() => _flooringType = value),
      ),
      _gap,
      _choiceChips(
        'وضعیت بازسازی',
        renovationStatuses,
        _renovationStatus,
        (value) => setState(() => _renovationStatus = value),
      ),
      _gap,
      _choiceChips(
        'جهت ساختمان',
        buildingOrientations,
        _buildingOrientation,
        (value) => setState(() => _buildingOrientation = value),
      ),
      _gap,
      if (_intent != 'rent')
        _choiceChips(
          'نوع سند',
          deedTypes,
          _deedType,
          (value) => setState(() => _deedType = value),
        ),
      const Divider(height: 28),
      if (_intent != 'rent') ...<Widget>[
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('وام'),
          value: _hasLoan,
          onChanged: (value) => setState(() => _hasLoan = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('قابل معاوضه'),
          value: _exchangeable,
          onChanged: (value) => setState(() => _exchangeable = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('استخر'),
          value: _pool,
          onChanged: (value) => setState(() => _pool = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('جکوزی'),
          value: _jacuzzi,
          onChanged: (value) => setState(() => _jacuzzi = value),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('سونا'),
          value: _sauna,
          onChanged: (value) => setState(() => _sauna = value),
        ),
      ],
    ],
  );

  Widget _choiceChips(
    String label,
    List<ChoiceItem> items,
    String? selected,
    ValueChanged<String?> onChanged,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      Text(label, style: Theme.of(context).textTheme.titleSmall),
      const SizedBox(height: 6),
      Wrap(
        spacing: 8,
        runSpacing: 6,
        children: items
            .map(
              (item) => ChoiceChip(
                label: Text(item.label),
                selected: selected == item.value,
                onSelected: (value) => onChanged(value ? item.value : null),
              ),
            )
            .toList(growable: false),
      ),
    ],
  );

  Widget _finalStep() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      _text('description', 'توضیحات', lines: 4),
      if (_editing && _status != _original?.status) ...<Widget>[
        _gap,
        _text(
          'status_reason',
          'دلیل تغییر وضعیت',
          required: _status == 'active' && _original?.status != 'active',
          lines: 2,
        ),
      ],
      const SizedBox(height: 12),
      const Card(
        child: ListTile(
          leading: Icon(Icons.info_outline),
          title: Text('ثبت اطلاعات مشتری'),
          subtitle: Text(
            'در نبود اینترنت، مشتری در صف امن دستگاه نگهداری و پس از اتصال همگام‌سازی می‌شود. ویرایش رکورد موجود فقط آنلاین است.',
          ),
        ),
      ),
      if (_error != null)
        Padding(
          padding: const EdgeInsets.only(top: 12),
          child: Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
    ],
  );

  Widget get _gap => const SizedBox(height: 12);
  TextFormField _text(
    String key,
    String label, {
    bool required = false,
    int lines = 1,
    TextInputType? keyboard,
    bool groupedNumber = false,
    bool integer = false,
  }) => TextFormField(
    controller: _c(key),
    minLines: lines,
    maxLines: lines,
    keyboardType: keyboard,
    inputFormatters: keyboard == TextInputType.phone
        ? const [PersianDigitsTextInputFormatter()]
        : groupedNumber
        ? [PersianNumberTextInputFormatter(integer: integer)]
        : null,
    decoration: InputDecoration(labelText: label),
    validator: (value) =>
        required && (value?.trim().isEmpty ?? true) ? 'الزامی است.' : null,
  );
  Widget _number(
    String key,
    String label, {
    bool required = false,
    bool integer = false,
    bool amount = false,
  }) {
    final field = TextFormField(
      controller: _c(key),
      keyboardType: TextInputType.numberWithOptions(decimal: !integer),
      inputFormatters: [PersianNumberTextInputFormatter(integer: integer)],
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        final text = normalizeNumericText(value?.trim() ?? '');
        if (required && text.isEmpty) {
          return 'الزامی است.';
        }
        if (text.isNotEmpty && num.tryParse(text) == null) {
          return 'عدد معتبر وارد کنید.';
        }
        return null;
      },
    );
    if (!amount) return field;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        field,
        AmountInWordsHint(controller: _c(key)),
      ],
    );
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final item = await ref
          .read(customerRepositoryProvider)
          .find(widget.customerId!);
      _original = item;
      _region = RegionSelection.fromJson(item.data, prefix: 'desired_');
      for (final entry in <String, Object?>{
        'full_name': item.data['full_name'],
        'mobile': item.data['mobile'],
        'phone': item.data['phone'],
        'city': item.data['desired_city'],
        'district': item.data['desired_district'],
        'area_min': item.data['min_area_sqm'],
        'area_max': item.data['max_area_sqm'],
        'bedrooms': item.data['min_bedrooms'],
        'parking_spaces': item.data['min_parking_spaces'],
        'budget_min': item.data['budget_min'],
        'budget_max': item.data['budget_max'],
        'deposit_min': item.data['rental_deposit_min'],
        'deposit_max': item.data['rental_deposit_max'],
        'rent_min': item.data['rental_rent_min'],
        'rent_max': item.data['rental_rent_max'],
        'description': item.data['description'],
      }.entries) {
        _c(entry.key).text = _loadedValue(entry.key, entry.value);
      }
      _intent = item.intent;
      _status = item.status;
      _propertyType = '${item.data['desired_property_type'] ?? 'apartment'}';
      _acceptsConversion = item.data['accepts_rent_conversion'] == true;
      _toiletTypes
        ..clear()
        ..addAll(_stringList(item.data['toilet_types']));
      _masterBathroom = item.data['has_master_bathroom'] == true;
      _cabinetType = item.data['cabinet_type'] as String?;
      _heatingType = item.data['heating_type'] as String?;
      _coolingType = item.data['cooling_type'] as String?;
      _flooringType = item.data['flooring_type'] as String?;
      _renovationStatus = item.data['renovation_status'] as String?;
      _buildingOrientation = item.data['building_orientation'] as String?;
      _deedType = item.data['deed_type'] as String?;
      _hasLoan = item.data['has_loan'] == true;
      _exchangeable = item.data['is_exchangeable'] == true;
      _pool = item.data['has_pool'] == true;
      _jacuzzi = item.data['has_jacuzzi'] == true;
      _sauna = item.data['has_sauna'] == true;
      _hasElevator = item.data['has_elevator'] == true;
      _hasBalcony = item.data['has_balcony'] == true;
      final legacyParking =
          num.tryParse(item.data['min_parking_spaces']?.toString() ?? '') ?? 0;
      _hasParking =
          item.data['has_parking'] == true ||
          (item.data['has_parking'] == null && legacyParking > 0);
      _hasStorageRoom = item.data['has_storage_room'] == true;
      _ownerResides = item.data['owner_resides'] == true;
      _buildingType = item.data['building_type'] as String?;
      _c('structure_type').text = '${item.data['structure_type'] ?? ''}';
      _c('telephone_line_count').text = _loadedValue(
        'telephone_line_count',
        item.data['telephone_line_count'],
      );
      final legacyLandArea = item.data['land_area'];
      final legacyBuildingArea = item.data['building_area'];
      _c('land_area_min').text = _loadedValue(
        'land_area_min',
        item.data['land_area_min'] ?? legacyLandArea,
      );
      _c('land_area_max').text = _loadedValue(
        'land_area_max',
        item.data['land_area_max'] ?? legacyLandArea,
      );
      _c('building_area_min').text = _loadedValue(
        'building_area_min',
        item.data['building_area_min'] ?? legacyBuildingArea,
      );
      _c('building_area_max').text = _loadedValue(
        'building_area_max',
        item.data['building_area_max'] ?? legacyBuildingArea,
      );
      _hasWater = item.data['has_water'] == true;
      _hasElectricity = item.data['has_electricity'] == true;
      _hasGas = item.data['has_gas'] == true;
    } catch (error) {
      _error = apiFailureFrom(error).displayMessage;
    }
    if (mounted) {
      setState(() => _loading = false);
    }
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (_editing && ref.read(authControllerProvider).isOffline) {
      setState(() => _error = 'ویرایش مشتری در حالت آفلاین در دسترس نیست.');
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      _goToStep(0);
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final body = <String, Object?>{
      'full_name': _raw('full_name'),
      'mobile': _v('mobile'),
      'phone': _empty('phone'),
      'preferred_contact_method': 'phone',
      'intent': _intent,
      'desired_property_type': _propertyType,
      'preferred_property_types': <String>[_propertyType],
      'desired_city': _region.cityName ?? _textOrNull('city'),
      ..._region.toJson(prefix: 'desired_'),
      'desired_district': _textOrNull('district'),
      'min_area_sqm': _isIndustrial ? null : _empty('area_min'),
      'max_area_sqm': _isIndustrial ? null : _empty('area_max'),
      'min_bedrooms': _int('bedrooms'),
      'min_parking_spaces': _intent == 'rent' && _hasParking ? 1 : null,
      'has_parking': _hasParking,
      'has_storage_room': _hasStorageRoom,
      'owner_resides': _intent == 'rent' && _ownerResides,
      'budget_min': _intent == 'buy' ? _empty('budget_min') : null,
      'budget_max': _intent == 'buy' ? _empty('budget_max') : null,
      'rental_deposit_min': _intent == 'rent' ? _v('deposit_min') : null,
      'rental_deposit_max': _intent == 'rent' ? _v('deposit_max') : null,
      'rental_rent_min': _intent == 'rent' ? _v('rent_min') : null,
      'rental_rent_max': _intent == 'rent' ? _v('rent_max') : null,
      'accepts_rent_conversion': _intent == 'rent' && _acceptsConversion,
      'toilet_types': _toiletTypes.isEmpty
          ? null
          : _toiletTypes.toList(growable: false),
      'has_master_bathroom': _showMasterBathroom && _masterBathroom,
      'cabinet_type': _cabinetType,
      'heating_type': _heatingType,
      'cooling_type': _coolingType,
      'flooring_type': _flooringType,
      'renovation_status': _renovationStatus,
      'building_orientation': _buildingOrientation,
      'deed_type': _intent == 'rent' ? null : _deedType,
      'has_loan': _intent == 'rent' ? false : _hasLoan,
      'is_exchangeable': _intent == 'rent' ? false : _exchangeable,
      'has_pool': _intent == 'rent' ? false : _pool,
      'has_jacuzzi': _intent == 'rent' ? false : _jacuzzi,
      'has_sauna': _intent == 'rent' ? false : _sauna,
      'has_elevator': _intent == 'rent' && _hasElevator,
      'has_balcony': _intent == 'rent' && _hasBalcony,
      'building_type': _buildingType,
      'structure_type': _textOrNull('structure_type'),
      'has_water': _hasWater,
      'has_electricity': _hasElectricity,
      'has_gas': _hasGas,
      'telephone_line_count': _empty('telephone_line_count'),
      'land_area': null,
      'building_area': null,
      'land_area_min': _isIndustrial ? _empty('land_area_min') : null,
      'land_area_max': _isIndustrial ? _empty('land_area_max') : null,
      'building_area_min': _isIndustrial ? _empty('building_area_min') : null,
      'building_area_max': _isIndustrial ? _empty('building_area_max') : null,
      'description': _textOrNull('description'),
    };
    try {
      if (_editing) {
        if (_status != _original!.status) {
          body['status'] = _status;
          body['reason'] = _textOrNull('status_reason');
        }
        body['expected_version'] = _original!.lockVersion;
        await ref
            .read(customerRepositoryProvider)
            .update(widget.customerId!, body);
        ref.invalidate(customerProvider(widget.customerId!));
      } else {
        await ref.read(customerRepositoryProvider).create(body);
      }
      ref.invalidate(customersProvider);
      ref.read(matchDataRevisionProvider.notifier).refresh();
      await ref.read(syncControllerProvider.notifier).refreshQueue();
      if (mounted) {
        context.pop(true);
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = apiFailureFrom(error).displayMessage;
        });
      }
    }
  }

  String _loadedValue(String key, Object? value) {
    if (value == null) return '';
    if (<String>{'mobile', 'phone'}.contains(key)) {
      return formatPersianDigits(value);
    }
    if (!_displayNumericFields.contains(key)) return '$value';
    return formatPersianNumberInput(
      '$value',
      integer: _integerNumericFields.contains(key),
    );
  }

  String _raw(String key) => _c(key).text.trim();

  String _v(String key) => normalizeNumericText(_raw(key));

  String? _textOrNull(String key) {
    final value = _raw(key);
    return value.isEmpty ? null : value;
  }

  String? _empty(String key) => _v(key).isEmpty ? null : _v(key);
  int? _int(String key) => int.tryParse(_v(key));

  void _clearIndustrialFields() {
    _hasWater = false;
    _hasElectricity = false;
    _hasGas = false;
    for (final key in <String>[
      'structure_type',
      'telephone_line_count',
      'land_area',
      'building_area',
      'land_area_min',
      'land_area_max',
      'building_area_min',
      'building_area_max',
    ]) {
      _c(key).clear();
    }
  }

  static List<String> _stringList(Object? value) => value is List
      ? value.map((item) => '$item').toList(growable: false)
      : const <String>[];
}
