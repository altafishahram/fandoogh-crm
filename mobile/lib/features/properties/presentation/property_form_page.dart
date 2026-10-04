import 'package:fandoogh_crm/features/geography/geography.dart';
import 'dart:io';

import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/localization/persian_number.dart';
import 'package:fandoogh_crm/core/offline/sync_controller.dart';
import 'package:fandoogh_crm/core/storage/android_media_picker.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/widgets/persian_date_field.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class PropertyFormPage extends ConsumerStatefulWidget {
  const PropertyFormPage({this.propertyId, super.key});
  final int? propertyId;
  @override
  ConsumerState<PropertyFormPage> createState() => _PropertyFormPageState();
}

final class _PropertyFormPageState extends ConsumerState<PropertyFormPage>
    with SingleTickerProviderStateMixin {
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _fields =
      <String, TextEditingController>{};
  late final AnimationController _stepAnimation;
  int _step = 0;
  int? _previousStep;
  int _stepDirection = 1;
  String _type = 'apartment';
  String _transaction = 'sale';
  String _delivery = 'ready';
  DateTime? _evacuationDate;
  DateTime? _availableFromDate;
  bool _convertible = false;
  bool _storage = false;
  bool _elevator = false;
  bool _balcony = false;
  bool _masterBathroom = false;
  bool _hasLoan = false;
  bool _exchangeable = false;
  bool _pool = false;
  bool _jacuzzi = false;
  bool _sauna = false;
  String? _buildingType;
  bool _hasWater = false;
  bool _hasElectricity = false;
  bool _hasGas = false;
  bool _canAggregate = false;
  final Set<String> _toiletTypes = <String>{};
  String? _cabinetType;
  String? _heatingType;
  String? _coolingType;
  String? _flooringType;
  String? _renovationStatus;
  String? _buildingOrientation;
  String? _deedType;
  bool _loading = false;
  bool _saving = false;
  bool _updatingPrice = false;
  RegionSelection _region = const RegionSelection();
  String? _error;
  PropertyRecord? _original;
  final List<String> _images = <String>[];
  String? _coverImagePath;
  static const _maxImages = 5;
  static const _displayNumericFields = <String>{
    'area',
    'sale_price',
    'price_per_sqm',
    'deposit',
    'rent',
    'minimum_deposit',
    'bedrooms',
    'floor',
    'total_floors',
    'units',
    'year',
    'parking',
    'land_frontage',
    'telephone_line_count',
    'land_area',
    'building_area',
  };
  static const _integerNumericFields = <String>{
    'bedrooms',
    'floor',
    'total_floors',
    'units',
    'year',
    'parking',
    'telephone_line_count',
  };
  bool get _editing => widget.propertyId != null;
  bool get _isLand => _type == 'land_old_building';
  bool get _isHouseVilla =>
      <String>{'house', 'villa', 'house_villa'}.contains(_type);
  bool get _isIndustrial => _type == 'industrial';
  List<ChoiceItem> get _transactionOptions => _isLand
      ? const <ChoiceItem>[ChoiceItem('sale', 'فروش')]
      : transactionTypes;
  List<ChoiceItem> get _deliveryOptions =>
      _transaction == 'sale' ? saleDeliveryStatuses : rentalDeliveryStatuses;
  bool get _deliveryDateRequired => _transaction == 'sale'
      ? _delivery == 'tenant_occupied' || _delivery == 'ready'
      : _delivery == 'dated';
  DateTime? get _deliveryDate => _transaction == 'sale' && _delivery == 'ready'
      ? _availableFromDate
      : _evacuationDate;
  String get _deliveryDateLabel {
    if (_transaction == 'sale' && _delivery == 'ready') {
      return 'تاریخ آماده تحویل';
    }
    if (_transaction == 'sale' && _delivery == 'tenant_occupied') {
      return 'تاریخ تخلیه مستأجر';
    }
    return 'تاریخ تخلیه';
  }

  static const _stepTitles = <String>[
    'اطلاعات اصلی',
    'مبلغ و نشانی',
    'مشخصات ساختمان',
    'مالک و تأیید',
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
    _c('sale_price').addListener(() => _calculatePerMeter(fromTotal: true));
    _c('price_per_sqm').addListener(() => _calculatePerMeter(fromTotal: false));
    _c('area').addListener(() => _calculatePerMeter(fromTotal: true));
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
    appBar: AppBar(title: Text(_editing ? 'ویرایش ملک' : 'ثبت ملک جدید')),
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
      _mainStep(),
      _priceStep(),
      _buildingStep(),
      _ownerStep(),
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
                key: ValueKey<String>('property-step-container-$index'),
                child: Offstage(
                  offstage: index != _step && index != _previousStep,
                  child: IgnorePointer(
                    ignoring: index != _step,
                    child: SlideTransition(
                      position: _stepPosition(index),
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
                        child: KeyedSubtree(
                          key: ValueKey<String>('property-step-$index'),
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
          onPressed: _step == 3
              ? (_saving ? null : _submit)
              : () => _goToStep(_step + 1),
          icon: Icon(
            _step == 3 ? Icons.save_outlined : Icons.arrow_back_rounded,
          ),
          label: Text(
            _saving
                ? 'در حال ثبت…'
                : _step == 3
                ? 'ذخیره ملک'
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

  Widget _mainStep() => Column(
    children: <Widget>[
      _text('title', 'عنوان ملک', required: true),
      _gap,
      ChoiceField(
        value: _transaction,
        label: 'نوع معامله',
        items: _transactionOptions,
        onChanged: (v) {
          if (!_editing) {
            setState(() {
              _transaction = v!;
              if (_transaction == 'rent') {
                _c('year').clear();
                _hasLoan = false;
                _exchangeable = false;
              }
              if (!_deliveryOptions.any((item) => item.value == _delivery)) {
                _delivery = 'ready';
                _evacuationDate = null;
                _availableFromDate = null;
              } else if (!_deliveryDateRequired) {
                _evacuationDate = null;
                _availableFromDate = null;
              }
            });
          }
        },
      ),
      _gap,
      ChoiceField(
        value: _type,
        label: 'نوع ملک',
        items: propertyTypes,
        onChanged: (v) => setState(() {
          _type = v!;
          if (_isLand) {
            _transaction = 'sale';
            _clearLandFields();
            if (!_deliveryOptions.any((item) => item.value == _delivery)) {
              _delivery = 'ready';
              _evacuationDate = null;
              _availableFromDate = null;
            }
          } else {
            _clearLandFields();
          }
          if (!_isHouseVilla) _buildingType = null;
          if (!_isIndustrial) _clearIndustrialFields();
        }),
      ),
      _gap,
      _text('description', 'توضیحات', lines: 3),
    ],
  );

  Widget _priceStep() => Column(
    children: <Widget>[
      _number('area', 'مساحت (متر مربع)', required: true),
      _gap,
      if (_transaction == 'sale') ...<Widget>[
        _number(
          'sale_price',
          'مبلغ کل فروش (تومان)',
          required: true,
          integer: true,
          amount: true,
        ),
        _gap,
        _number(
          'price_per_sqm',
          'مبلغ هر متر (تومان)',
          required: true,
          integer: true,
          amount: true,
        ),
        const Padding(
          padding: EdgeInsets.only(top: 6),
          child: Text(
            'هر کدام را تغییر دهید، مبلغ دیگر با گردکردن معمولی محاسبه می‌شود.',
          ),
        ),
        if (_delivery == 'tenant_occupied') ...<Widget>[
          _gap,
          _number(
            'deposit',
            'مبلغ ودیعه مستأجر (تومان)',
            integer: true,
            amount: true,
          ),
          _gap,
          _number(
            'rent',
            'مبلغ اجاره مستأجر (تومان)',
            integer: true,
            amount: true,
          ),
        ],
      ] else ...<Widget>[
        _number(
          'deposit',
          'ودیعه (تومان)',
          required: true,
          integer: true,
          amount: true,
        ),
        _gap,
        _number(
          'rent',
          'اجاره ماهانه (تومان)',
          required: true,
          integer: true,
          amount: true,
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('قابل تبدیل ودیعه و اجاره'),
          subtitle: const Text(
            'هر یک میلیون تومان ودیعه برابر سی هزار تومان اجاره',
          ),
          value: _convertible,
          onChanged: (v) => setState(() => _convertible = v),
        ),
        if (_convertible)
          _number(
            'minimum_deposit',
            'حداقل ودیعه (تومان)',
            required: true,
            integer: true,
            amount: true,
          ),
      ],
      _gap,
      RegionFields(
        value: _region,
        requiredCity: true,
        legacyCity: _editing ? _c('city').text : null,
        onChanged: (region) => setState(() {
          _region = region;
          _c('city').text = region.cityName ?? '';
          _c('district').clear();
        }),
      ),
      _gap,
      _text('district', 'محله'),
      _gap,
      _text('address', 'نشانی', required: true, lines: 2),
      _gap,
      _text('plaque', 'شماره پلاک نشانی', required: !_editing),
      _gap,
      ChoiceField(
        value: _delivery,
        label: 'وضعیت تخلیه',
        items: _deliveryOptions,
        onChanged: (v) => setState(() {
          _delivery = v!;
          if (_transaction == 'sale') {
            if (_delivery != 'tenant_occupied') _evacuationDate = null;
            if (_delivery != 'ready') _availableFromDate = null;
          } else if (_delivery != 'dated') {
            _evacuationDate = null;
            _availableFromDate = null;
          }
        }),
      ),
      if (_deliveryDateRequired) ...<Widget>[
        _gap,
        PersianDateField(
          label: _deliveryDateLabel,
          value: _deliveryDate,
          onChanged: (value) => setState(() {
            if (_transaction == 'sale' && _delivery == 'ready') {
              _availableFromDate = value;
            } else {
              _evacuationDate = value;
            }
          }),
        ),
      ],
    ],
  );

  Widget _buildingStep() {
    if (_isLand) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          _choiceChips(
            'نوع سند',
            deedTypes,
            _deedType,
            (value) => setState(() => _deedType = value),
          ),
          const Divider(height: 28),
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
            title: const Text('قابلیت تجمیع'),
            value: _canAggregate,
            onChanged: (value) => setState(() => _canAggregate = value),
          ),
          _text(
            'land_frontage',
            'حد زمین (بر)',
            suffixText: 'متر',
            groupedNumber: true,
          ),
        ],
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(child: _number('bedrooms', 'اتاق', integer: true)),
          ],
        ),
        _gap,
        Row(
          children: <Widget>[
            Expanded(child: _number('floor', 'طبقه', integer: true)),
            const SizedBox(width: 8),
            Expanded(
              child: _number('total_floors', 'تعداد طبقات', integer: true),
            ),
            const SizedBox(width: 8),
            Expanded(child: _number('units', 'واحد در طبقه', integer: true)),
          ],
        ),
        _gap,
        if (_transaction != 'rent')
          Row(
            children: <Widget>[
              Expanded(child: _number('year', 'سال ساخت', integer: true)),
              const SizedBox(width: 8),
              Expanded(
                child: _number('parking', 'تعداد پارکینگ', integer: true),
              ),
            ],
          )
        else
          _number('parking', 'تعداد پارکینگ', integer: true),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('انباری'),
          value: _storage,
          onChanged: (v) => setState(() => _storage = v),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('آسانسور'),
          value: _elevator,
          onChanged: (v) => setState(() => _elevator = v),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('بالکن'),
          value: _balcony,
          onChanged: (v) => setState(() => _balcony = v),
        ),
        const Divider(height: 28),
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
          _text('land_area', 'متراژ زمین', groupedNumber: true),
          _gap,
          _text('building_area', 'متراژ بنا', groupedNumber: true),
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
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('سرویس مستر'),
          value: _masterBathroom,
          onChanged: (v) => setState(() => _masterBathroom = v),
        ),
        ChoiceField(
          value: _cabinetType,
          label: 'نوع کابینت',
          items: cabinetTypes,
          includeEmpty: true,
          onChanged: (v) => setState(() => _cabinetType = v),
        ),
        _gap,
        ChoiceField(
          value: _heatingType,
          label: 'نوع گرمایش',
          items: heatingTypes,
          includeEmpty: true,
          onChanged: (v) => setState(() => _heatingType = v),
        ),
        _gap,
        ChoiceField(
          value: _coolingType,
          label: 'نوع سرمایش',
          items: coolingTypes,
          includeEmpty: true,
          onChanged: (v) => setState(() => _coolingType = v),
        ),
        _gap,
        ChoiceField(
          value: _flooringType,
          label: 'نوع کف‌پوش',
          items: flooringTypes,
          includeEmpty: true,
          onChanged: (v) => setState(() => _flooringType = v),
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
        _choiceChips(
          'نوع سند',
          deedTypes,
          _deedType,
          (value) => setState(() => _deedType = value),
        ),
        const Divider(height: 28),
        if (_transaction != 'rent') ...<Widget>[
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('وام'),
            value: _hasLoan,
            onChanged: (v) => setState(() => _hasLoan = v),
          ),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('قابل معاوضه'),
            value: _exchangeable,
            onChanged: (v) => setState(() => _exchangeable = v),
          ),
        ],
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('استخر'),
          value: _pool,
          onChanged: (v) => setState(() => _pool = v),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('جکوزی'),
          value: _jacuzzi,
          onChanged: (v) => setState(() => _jacuzzi = v),
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('سونا'),
          value: _sauna,
          onChanged: (v) => setState(() => _sauna = v),
        ),
      ],
    );
  }

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

  Widget _ownerStep() => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      if (_editing)
        Card(
          child: ListTile(
            leading: const Icon(Icons.person_outline),
            title: Text(
              _original?.owners.isEmpty == false
                  ? '${_original!.owners.first['full_name'] ?? ''}'
                  : 'مالک ثبت نشده',
            ),
            subtitle: const Text('اطلاعات مالک در ویرایش ملک تغییر نمی‌کند.'),
          ),
        )
      else ...<Widget>[
        _text('owner_name', 'نام کامل مالک', required: true),
        _gap,
        _text(
          'owner_mobile',
          'شماره همراه مالک',
          required: true,
          keyboard: TextInputType.phone,
        ),
        _gap,
        _text('owner_phone', 'تلفن ثابت مالک', keyboard: TextInputType.phone),
        _gap,
        _text('owner_notes', 'توضیحات مالک', lines: 2),
      ],
      const SizedBox(height: 12),
      if (!_editing)
        Card(
          child: Column(
            children: <Widget>[
              const ListTile(
                leading: Icon(Icons.photo_library_outlined),
                title: Text('تصاویر ملک'),
                subtitle: Text(
                  'اختیاری، حداکثر پنج تصویر؛ نخستین تصویر به‌صورت خودکار کاور می‌شود.',
                ),
              ),
              if (_images.isEmpty)
                const Padding(
                  padding: EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: Text('هنوز تصویری انتخاب نشده است.'),
                  ),
                ),
              if (_images.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate:
                        const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 3,
                          crossAxisSpacing: 8,
                          mainAxisSpacing: 8,
                          childAspectRatio: .82,
                        ),
                    itemCount:
                        _images.length + (_images.length < _maxImages ? 1 : 0),
                    itemBuilder: (context, index) => index == _images.length
                        ? _addImageTile()
                        : _imageTile(_images[index], index),
                  ),
                ),
              if (_images.isEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: _addImageButton(),
                ),
              if (_images.isNotEmpty && _images.length >= _maxImages)
                const Padding(
                  padding: EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: Align(
                    alignment: Alignment.centerRight,
                    child: Text('حداکثر پنج تصویر انتخاب شده است.'),
                  ),
                ),
            ],
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
    String? suffixText,
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
    decoration: InputDecoration(labelText: label, suffixText: suffixText),
    validator: (value) => required && (value?.trim().isEmpty ?? true)
        ? 'این فیلد الزامی است.'
        : null,
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
          return 'این فیلد الزامی است.';
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

  void _calculatePerMeter({required bool fromTotal}) {
    if (_updatingPrice || _transaction != 'sale') {
      return;
    }
    final area = num.tryParse(normalizeNumericText(_c('area').text));
    if (area == null || area <= 0) {
      return;
    }
    final source = num.tryParse(
      normalizeNumericText(_c(fromTotal ? 'sale_price' : 'price_per_sqm').text),
    );
    if (source == null) {
      return;
    }
    _updatingPrice = true;
    _c(
      fromTotal ? 'price_per_sqm' : 'sale_price',
    ).text = formatPersianNumberInput(
      (fromTotal ? source / area : source * area).round().toString(),
      integer: true,
    );
    _updatingPrice = false;
  }

  Widget _addImageButton() => OutlinedButton.icon(
    onPressed: _pickImages,
    icon: const Icon(Icons.add_photo_alternate_outlined),
    label: const Text('افزودن تصویر'),
  );

  Widget _addImageTile() => Material(
    color: Theme.of(context).colorScheme.surfaceContainerHighest,
    borderRadius: BorderRadius.circular(12),
    child: InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: _pickImages,
      child: const Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(Icons.add_photo_alternate_outlined),
          SizedBox(height: 6),
          Text('افزودن تصویر', textAlign: TextAlign.center),
        ],
      ),
    ),
  );

  Widget _imageTile(String path, int index) {
    final isCover = _coverImagePath == path;
    final colors = Theme.of(context).colorScheme;
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: colors.surfaceContainerHighest,
          border: Border.all(
            color: isCover ? colors.primary : colors.outlineVariant,
            width: isCover ? 2 : 1,
          ),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            Image.file(
              File(path),
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  const Center(child: Icon(Icons.broken_image_outlined)),
            ),
            Positioned.fill(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: <Color>[
                      Colors.black.withValues(alpha: .18),
                      Colors.transparent,
                      Colors.black.withValues(alpha: .62),
                    ],
                  ),
                ),
              ),
            ),
            Positioned(
              top: 5,
              left: 5,
              child: Tooltip(
                message: 'حذف تصویر ${persianDigits(index + 1)}',
                child: Material(
                  color: Colors.black.withValues(alpha: .58),
                  shape: const CircleBorder(),
                  child: InkWell(
                    customBorder: const CircleBorder(),
                    onTap: () => _removeImage(path),
                    child: const Padding(
                      padding: EdgeInsets.all(5),
                      child: Icon(
                        Icons.close_rounded,
                        color: Colors.white,
                        size: 18,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Positioned(
              right: 5,
              left: 5,
              bottom: 5,
              child: Material(
                color: isCover
                    ? colors.primary
                    : Colors.black.withValues(alpha: .58),
                borderRadius: BorderRadius.circular(8),
                child: InkWell(
                  borderRadius: BorderRadius.circular(8),
                  onTap: isCover ? null : () => _selectCover(path),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 5,
                      vertical: 5,
                    ),
                    child: Text(
                      isCover ? '★ کاور' : 'انتخاب کاور',
                      textAlign: TextAlign.center,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _removeImage(String path) {
    setState(() {
      final wasCover = _coverImagePath == path;
      _images.remove(path);
      if (wasCover) {
        _coverImagePath = _images.isEmpty ? null : _images.first;
      } else if (_coverImagePath != null &&
          !_images.contains(_coverImagePath)) {
        _coverImagePath = _images.isEmpty ? null : _images.first;
      }
    });
  }

  void _selectCover(String path) {
    if (!_images.contains(path) || _coverImagePath == path) return;
    setState(() => _coverImagePath = path);
  }

  Future<void> _pickImages() async {
    final remaining = _maxImages - _images.length;
    if (remaining <= 0) return;
    try {
      final selected = await AndroidMediaPicker.pickImages(maxCount: remaining);
      if (!mounted || selected.isEmpty) return;

      final additions = <String>[];
      for (final path in selected) {
        if (path.isEmpty ||
            _images.contains(path) ||
            additions.contains(path)) {
          continue;
        }
        additions.add(path);
        if (additions.length == remaining) break;
      }

      setState(() {
        _images.addAll(additions);
        if (additions.isNotEmpty &&
            (_coverImagePath == null || !_images.contains(_coverImagePath))) {
          _coverImagePath = additions.first;
        }
        if (selected.length > additions.length) {
          _error = 'حداکثر پنج تصویر مجاز است؛ فقط تصاویر مجاز اضافه شدند.';
        } else {
          _error = null;
        }
      });
    } catch (_) {
      if (mounted) {
        setState(() => _error = 'انتخاب تصویر روی این دستگاه انجام نشد.');
      }
    }
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final item = await ref
          .read(propertyRepositoryProvider)
          .find(widget.propertyId!);
      _original = item;
      _region = RegionSelection.fromJson(item.data, prefix: '');
      for (final entry in <String, Object?>{
        'title': item.data['title'],
        'description': item.data['description'],
        'sale_price': item.data['sale_price'],
        'price_per_sqm': item.data['sale_price_per_sqm'],
        'deposit': item.data['deposit_amount'],
        'rent': item.data['monthly_rent'],
        'minimum_deposit': item.data['minimum_deposit'],
        'area': item.data['area_sqm'],
        'city': item.data['city'],
        'district': item.data['district'],
        'address': item.data['street_address'],
        'plaque': item.data['plaque'],
        'bedrooms': item.data['bedrooms'],
        'floor': item.data['floor_number'],
        'total_floors': item.data['total_floors'],
        'units': item.data['units_per_floor'],
        'year': item.data['year_built'],
        'parking': item.data['parking_spaces'],
      }.entries) {
        _c(entry.key).text = _loadedValue(entry.key, entry.value);
      }
      _type = item.propertyType;
      _transaction = item.transactionType;
      _delivery = '${item.data['delivery_status'] ?? 'ready'}';
      _evacuationDate = DateTime.tryParse(
        '${item.data['evacuation_date'] ?? ''}',
      );
      _availableFromDate = DateTime.tryParse(
        '${item.data['available_from'] ?? ''}',
      );
      if (!_deliveryOptions.any((option) => option.value == _delivery)) {
        _delivery = 'ready';
        _availableFromDate ??= _evacuationDate;
        _evacuationDate = null;
      }
      _convertible = item.data['is_convertible'] == true;
      _storage = item.data['has_storage_room'] == true;
      _elevator = item.data['has_elevator'] == true;
      _balcony = item.data['has_balcony'] == true;
      _masterBathroom = item.data['has_master_bathroom'] == true;
      _hasLoan = item.data['has_loan'] == true;
      _exchangeable = item.data['is_exchangeable'] == true;
      _pool = item.data['has_pool'] == true;
      _jacuzzi = item.data['has_jacuzzi'] == true;
      _sauna = item.data['has_sauna'] == true;
      _toiletTypes
        ..clear()
        ..addAll(_stringList(item.data['toilet_types']));
      _cabinetType = item.data['cabinet_type'] as String?;
      _heatingType = _singleValue(
        item.data['heating_type'],
        item.data['heating_systems'],
      );
      _coolingType = _singleValue(
        item.data['cooling_type'],
        item.data['cooling_systems'],
      );
      _flooringType = item.data['flooring_type'] as String?;
      _renovationStatus = item.data['renovation_status'] as String?;
      _buildingOrientation = item.data['building_orientation'] as String?;
      _deedType = item.data['deed_type'] as String?;
      _buildingType = item.data['building_type'] as String?;
      _c('structure_type').text = '${item.data['structure_type'] ?? ''}';
      _c('telephone_line_count').text = _loadedValue(
        'telephone_line_count',
        item.data['telephone_line_count'],
      );
      _c('land_area').text = _loadedValue('land_area', item.data['land_area']);
      _c('building_area').text = _loadedValue(
        'building_area',
        item.data['building_area'],
      );
      _c('land_frontage').text = _loadedValue(
        'land_frontage',
        item.data['land_frontage'],
      );
      _canAggregate = item.data['can_aggregate'] == true;
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
      setState(() => _error = 'ویرایش ملک در حالت آفلاین در دسترس نیست.');
      return;
    }
    if (!(_formKey.currentState?.validate() ?? false)) {
      _goToStep(0);
      return;
    }
    if (_deliveryDateRequired && _deliveryDate == null) {
      _goToStep(1);
      setState(() {
        _error = '$_deliveryDateLabel را انتخاب کنید.';
      });
      return;
    }
    if (_images.isNotEmpty && ref.read(authControllerProvider).isOffline) {
      setState(
        () => _error =
            'ثبت ملک همراه تصویر در حالت آفلاین ممکن نیست؛ تصاویر را حذف کنید یا پس از اتصال دوباره تلاش کنید.',
      );
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final tenantOccupiedSale =
        _transaction == 'sale' && _delivery == 'tenant_occupied';
    final body = <String, Object?>{
      'title': _raw('title'),
      'description': _textOrNull('description'),
      'property_type': _type,
      'transaction_type': _transaction,
      'area_sqm': _v('area'),
      'sale_price': _transaction == 'sale' ? _v('sale_price') : null,
      'sale_price_per_sqm': _transaction == 'sale' ? _v('price_per_sqm') : null,
      'price_input_mode': 'total',
      'deposit_amount': _transaction == 'rent' || tenantOccupiedSale
          ? _v('deposit')
          : null,
      'monthly_rent': _transaction == 'rent' || tenantOccupiedSale
          ? _v('rent')
          : null,
      'is_convertible': _transaction == 'rent' && _convertible,
      'minimum_deposit': _transaction == 'rent' && _convertible
          ? _v('minimum_deposit')
          : null,
      'city': _region.cityName ?? _raw('city'),
      ..._region.toJson(prefix: ''),
      'district': _textOrNull('district'),
      'street_address': _raw('address'),
      'plaque': _textOrNull('plaque'),
      'delivery_status': _delivery,
      'available_from': _transaction == 'sale' && _delivery == 'ready'
          ? PersianDate.fromGregorian(_availableFromDate!).isoDate
          : null,
      'evacuation_date':
          (_transaction == 'sale' && _delivery == 'tenant_occupied') ||
              (_transaction == 'rent' && _delivery == 'dated')
          ? PersianDate.fromGregorian(_evacuationDate!).isoDate
          : null,
      if (!_isLand) ...<String, Object?>{
        'bedrooms': _int('bedrooms'),
        'floor_number': _int('floor'),
        'total_floors': _int('total_floors'),
        'units_per_floor': _int('units'),
        'year_built': _transaction == 'sale' ? _int('year') : null,
        'parking_spaces': _int('parking') ?? 0,
        'has_storage_room': _storage,
        'has_elevator': _elevator,
        'has_balcony': _balcony,
      },
      'toilet_types': _isLand || _toiletTypes.isEmpty
          ? null
          : _toiletTypes.toList(growable: false),
      'has_master_bathroom': !_isLand && _masterBathroom,
      'cabinet_type': _isLand ? null : _cabinetType,
      'heating_type': _isLand ? null : _heatingType,
      'cooling_type': _isLand ? null : _coolingType,
      'flooring_type': _isLand ? null : _flooringType,
      'renovation_status': _isLand ? null : _renovationStatus,
      'building_orientation': _isLand ? null : _buildingOrientation,
      'deed_type': _deedType,
      'has_loan': _isLand || _transaction == 'sale' ? _hasLoan : false,
      'is_exchangeable': _isLand || _transaction == 'sale'
          ? _exchangeable
          : false,
      'has_pool': !_isLand && _pool,
      'has_jacuzzi': !_isLand && _jacuzzi,
      'has_sauna': !_isLand && _sauna,
      'building_type': _isLand ? null : _buildingType,
      'structure_type': _isLand ? null : _textOrNull('structure_type'),
      'has_water': !_isLand && _hasWater,
      'has_electricity': !_isLand && _hasElectricity,
      'has_gas': !_isLand && _hasGas,
      'telephone_line_count': _isLand ? null : _empty('telephone_line_count'),
      'land_area': _isLand ? null : _empty('land_area'),
      'building_area': _isLand ? null : _empty('building_area'),
      'can_aggregate': _isLand && _canAggregate,
      'land_frontage': _isLand ? _empty('land_frontage') : null,
    };
    try {
      if (_editing) {
        body['expected_version'] = _original!.lockVersion;
        await ref
            .read(propertyRepositoryProvider)
            .update(widget.propertyId!, body);
        ref.invalidate(propertyProvider(widget.propertyId!));
      } else {
        body['owner'] = <String, Object?>{
          'full_name': _raw('owner_name'),
          'mobile': _v('owner_mobile'),
          'phone': _empty('owner_phone'),
          'notes': _textOrNull('owner_notes'),
        };
        final created = await ref
            .read(propertyRepositoryProvider)
            .create(body, allowOffline: _images.isEmpty);
        if (created.id > 0) {
          final repository = ref.read(propertyRepositoryProvider);
          final uploadedImages = <Map<String, dynamic>>[];
          for (final image in _images) {
            uploadedImages.add(await repository.addImage(created.id, image));
          }

          final coverIndex = _coverImagePath == null
              ? 0
              : _images.indexOf(_coverImagePath!);
          if (coverIndex > 0 && coverIndex < uploadedImages.length) {
            final uploaded = uploadedImages[coverIndex];
            final imageId = (uploaded['id'] as num?)?.toInt();
            final updatedAt = uploaded['updated_at'];
            if (imageId == null || updatedAt is! String || updatedAt.isEmpty) {
              throw StateError('اطلاعات تصویر کاور از سرور کامل نیست.');
            }
            await repository.updateImage(
              created.id,
              imageId,
              sortOrder: coverIndex,
              isCover: true,
              expectedUpdatedAt: updatedAt,
            );
          }
        }
      }
      ref.invalidate(propertiesProvider);
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
    if (<String>{'owner_mobile', 'owner_phone'}.contains(key)) {
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
    ]) {
      _c(key).clear();
    }
  }

  void _clearLandFields() {
    _canAggregate = false;
    _c('land_frontage').clear();
  }

  static List<String> _stringList(Object? value) => value is List
      ? value.map((item) => '$item').where((item) => item.isNotEmpty).toList()
      : const <String>[];

  static String? _singleValue(Object? value, Object? legacyValues) {
    if (value is String && value.isNotEmpty) return value;
    final legacy = _stringList(legacyValues);
    return legacy.isEmpty ? null : legacy.first;
  }
}
