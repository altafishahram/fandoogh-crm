import 'package:flutter/material.dart';

final class ChoiceItem {
  const ChoiceItem(this.value, this.label);
  final String value;
  final String label;
}

class ChoiceField extends StatefulWidget {
  const ChoiceField({
    required this.value,
    required this.label,
    required this.items,
    required this.onChanged,
    this.includeEmpty = false,
    this.required = false,
    super.key,
  });
  final String? value;
  final String label;
  final List<ChoiceItem> items;
  final ValueChanged<String?> onChanged;
  final bool includeEmpty;
  final bool required;

  @override
  State<ChoiceField> createState() => _ChoiceFieldState();
}

final class _ChoiceFieldState extends State<ChoiceField> {
  final _anchorKey = GlobalKey();
  final _layerLink = LayerLink();
  OverlayEntry? _menuEntry;
  FormFieldState<String>? _fieldState;
  double _fieldWidth = 0;

  @override
  void didUpdateWidget(covariant ChoiceField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _fieldState?.didChange(widget.value);
      });
    }
  }

  @override
  void dispose() {
    _menuEntry?.remove();
    _menuEntry = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FormField<String>(
    initialValue: widget.value,
    validator: (value) => widget.required && (value == null || value.isEmpty)
        ? 'انتخاب این مورد الزامی است.'
        : null,
    builder: (field) {
      _fieldState = field;
      final selected = widget.value;
      final selectedLabel = selected == null
          ? (widget.includeEmpty ? 'انتخاب نشده' : '')
          : widget.items
                    .where((item) => item.value == selected)
                    .map((item) => item.label)
                    .firstOrNull ??
                selected;
      final hasDisplayValue = selectedLabel.isNotEmpty;
      final theme = Theme.of(context);
      return CompositedTransformTarget(
        link: _layerLink,
        child: Semantics(
          button: true,
          label: widget.label,
          child: InkWell(
            key: _anchorKey,
            borderRadius: BorderRadius.circular(4),
            onTap: _handleTap,
            child: InputDecorator(
              // «انتخاب نشده» is a visible value, so the label must float
              // instead of being painted over the placeholder.
              isEmpty: !hasDisplayValue,
              decoration: InputDecoration(
                labelText: widget.label,
                border: const OutlineInputBorder(),
                errorText: field.errorText,
                suffixIcon: Icon(
                  _menuEntry == null
                      ? Icons.keyboard_arrow_down_rounded
                      : Icons.keyboard_arrow_up_rounded,
                ),
              ),
              child: Text(
                selectedLabel,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                softWrap: false,
                style: TextStyle(
                  color: selected == null
                      ? theme.colorScheme.onSurfaceVariant
                      : theme.colorScheme.onSurface,
                ),
              ),
            ),
          ),
        ),
      );
    },
  );

  void _handleTap() {
    if (_menuEntry == null) {
      _openMenu();
    } else {
      _closeMenu();
    }
  }

  Future<void> _openMenu() async {
    final anchorContext = _anchorKey.currentContext;
    if (anchorContext != null) {
      await Scrollable.ensureVisible(
        anchorContext,
        alignment: 0.12,
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
      );
    }
    if (!mounted) return;

    final renderObject = _anchorKey.currentContext?.findRenderObject();
    if (renderObject is! RenderBox || !renderObject.hasSize) return;
    _fieldWidth = renderObject.size.width;
    final bottom = renderObject
        .localToGlobal(Offset(0, renderObject.size.height))
        .dy;
    final remainingBelow = MediaQuery.sizeOf(context).height - bottom - 12;
    final menuHeight = remainingBelow.clamp(120.0, 360.0).toDouble();
    final theme = Theme.of(context);
    final overlay = Overlay.of(context, rootOverlay: true);
    late final OverlayEntry entry;
    entry = OverlayEntry(
      builder: (_) => Positioned.fill(
        child: Stack(
          clipBehavior: Clip.none,
          children: <Widget>[
            Positioned.fill(
              child: GestureDetector(
                behavior: HitTestBehavior.translucent,
                onTap: _closeMenu,
              ),
            ),
            CompositedTransformFollower(
              link: _layerLink,
              showWhenUnlinked: false,
              targetAnchor: Alignment.bottomLeft,
              followerAnchor: Alignment.topLeft,
              offset: const Offset(0, 4),
              child: Material(
                color: theme.colorScheme.surface,
                elevation: 8,
                clipBehavior: Clip.antiAlias,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                  side: BorderSide(color: theme.colorScheme.outlineVariant),
                ),
                child: SizedBox(
                  width: _fieldWidth,
                  child: ConstrainedBox(
                    constraints: BoxConstraints(maxHeight: menuHeight),
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      shrinkWrap: true,
                      itemCount: _options.length,
                      itemBuilder: (context, index) {
                        final item = _options[index];
                        final selected = item.value == widget.value;
                        return InkWell(
                          onTap: () => _select(item.value),
                          child: Container(
                            color: selected
                                ? theme.colorScheme.primaryContainer
                                : null,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 13,
                            ),
                            child: Text(item.label),
                          ),
                        );
                      },
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
    _menuEntry = entry;
    overlay.insert(entry);
    setState(() {});
  }

  List<ChoiceItem> get _options => <ChoiceItem>[
    if (widget.includeEmpty) const ChoiceItem('', 'انتخاب نشده'),
    ...widget.items,
  ];

  void _select(String value) {
    final selected = value.isEmpty ? null : value;
    _fieldState?.didChange(selected);
    widget.onChanged(selected);
    _closeMenu();
  }

  void _closeMenu() {
    final entry = _menuEntry;
    if (entry == null) return;
    _menuEntry = null;
    entry.remove();
    if (mounted) setState(() {});
  }
}

const propertyTypes = <ChoiceItem>[
  ChoiceItem('apartment', 'آپارتمان'),
  ChoiceItem('office', 'اداری'),
  ChoiceItem('house', 'خانه'),
  ChoiceItem('villa', 'ویلا'),
  ChoiceItem('land_old_building', 'زمین و ملک کلنگی'),
  ChoiceItem('bureau', 'دفتر'),
  ChoiceItem('industrial', 'صنعتی'),
];
const transactionTypes = <ChoiceItem>[
  ChoiceItem('sale', 'فروش'),
  ChoiceItem('rent', 'اجاره'),
];
const propertyStatuses = <ChoiceItem>[
  ChoiceItem('available', 'موجود'),
  ChoiceItem('reserved', 'رزرو'),
  ChoiceItem('sold', 'فروخته‌شده'),
  ChoiceItem('rented', 'اجاره‌رفته'),
  ChoiceItem('archived', 'بایگانی'),
];
const customerStatuses = <ChoiceItem>[
  ChoiceItem('active', 'فعال'),
  ChoiceItem('finalized', 'نهایی‌شده'),
  ChoiceItem('withdrawn', 'انصراف‌داده'),
  ChoiceItem('transacted_elsewhere', 'معامله در جای دیگر'),
];
const customerIntents = <ChoiceItem>[
  ChoiceItem('buy', 'خرید'),
  ChoiceItem('rent', 'اجاره'),
];
const deliveryStatuses = <ChoiceItem>[
  ChoiceItem('owner_occupied', 'مالک ساکن'),
  ChoiceItem('tenant_occupied', 'مستأجر ساکن'),
  ChoiceItem('ready', 'آماده تحویل'),
  ChoiceItem('vacated', 'تخلیه‌شده'),
  ChoiceItem('dated', 'دارای تاریخ تخلیه'),
];
const saleDeliveryStatuses = <ChoiceItem>[
  ChoiceItem('owner_occupied', 'مالک ساکن'),
  ChoiceItem('tenant_occupied', 'مستأجر ساکن'),
  ChoiceItem('ready', 'آماده تحویل'),
  ChoiceItem('vacated', 'تخلیه‌شده'),
];
const rentalDeliveryStatuses = <ChoiceItem>[
  ChoiceItem('ready', 'آماده تحویل'),
  ChoiceItem('vacated', 'تخلیه‌شده'),
  ChoiceItem('dated', 'دارای تاریخ تخلیه'),
];

const toiletTypes = <ChoiceItem>[
  ChoiceItem('iranian', 'ایرانی'),
  ChoiceItem('western', 'فرنگی'),
];
const cabinetTypes = <ChoiceItem>[
  ChoiceItem('mdf', 'MDF'),
  ChoiceItem('high_gloss', 'های‌گلاس'),
  ChoiceItem('membrane', 'ممبران'),
  ChoiceItem('wood', 'چوب'),
  ChoiceItem('metal', 'فلزی'),
  ChoiceItem('metal_wood_doors', 'فلزی درب چوبی'),
  ChoiceItem('other', 'سایر'),
];
const heatingTypes = <ChoiceItem>[
  ChoiceItem('heater', 'بخاری'),
  ChoiceItem('radiator', 'شوفاژ'),
  ChoiceItem('floor_heating', 'گرمایش از کف'),
  ChoiceItem('duct_split', 'داکت اسپلیت'),
  ChoiceItem('fireplace', 'شومینه'),
  ChoiceItem('split', 'اسپلیت'),
  ChoiceItem('fan_coil', 'فن کوئل'),
];
const coolingTypes = <ChoiceItem>[
  ChoiceItem('water_cooler', 'کولر آبی'),
  ChoiceItem('air_conditioner', 'کولر گازی'),
  ChoiceItem('duct_split', 'داکت اسپلیت'),
  ChoiceItem('split', 'اسپلیت'),
  ChoiceItem('other', 'سایر'),
];
const flooringTypes = <ChoiceItem>[
  ChoiceItem('ceramic', 'سرامیک'),
  ChoiceItem('stone', 'سنگ'),
  ChoiceItem('carpet', 'موکت'),
  ChoiceItem('parquet', 'پارکت'),
  ChoiceItem('pvc', 'کف‌پوش PVC'),
  ChoiceItem('mosaic', 'موزائیک'),
  ChoiceItem('cement', 'سیمان'),
];
const renovationStatuses = <ChoiceItem>[
  ChoiceItem('new', 'نوساز'),
  ChoiceItem('renovated', 'بازسازی‌شده'),
  ChoiceItem('needs_renovation', 'نیاز به بازسازی'),
  ChoiceItem('painted', 'رنگ‌شده'),
  ChoiceItem('wallpaper', 'کاغذ دیواری'),
];
const buildingOrientations = <ChoiceItem>[
  ChoiceItem('north', 'شمالی'),
  ChoiceItem('south', 'جنوبی'),
  ChoiceItem('east', 'شرقی'),
  ChoiceItem('west', 'غربی'),
];
const deedTypes = <ChoiceItem>[
  ChoiceItem('single_page', 'سند تک‌برگ'),
  ChoiceItem('booklet', 'منگوله‌دار'),
  ChoiceItem('contract', 'قولنامه‌ای'),
  ChoiceItem('other', 'سایر'),
];

const buildingTypes = <ChoiceItem>[
  ChoiceItem('detached', 'ویلای مستقل'),
  ChoiceItem('township', 'شهرکی'),
  ChoiceItem('duplex', 'دوبلکس'),
  ChoiceItem('triplex', 'تریبلکس'),
  ChoiceItem('apartment_villa', 'ویلا آپارتمانی'),
];

String labelOf(List<ChoiceItem> items, String? value) =>
    items
        .where((item) => item.value == value)
        .map((item) => item.label)
        .firstOrNull ??
    value ??
    '—';
