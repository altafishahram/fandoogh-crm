import 'package:flutter/material.dart';

final class ChoiceItem {
  const ChoiceItem(this.value, this.label);
  final String value;
  final String label;
}

class ChoiceField extends StatelessWidget {
  const ChoiceField({
    required this.value,
    required this.label,
    required this.items,
    required this.onChanged,
    super.key,
  });
  final String? value;
  final String label;
  final List<ChoiceItem> items;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) => DropdownButtonFormField<String>(
    initialValue: value,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    items: items
        .map(
          (item) => DropdownMenuItem<String>(
            value: item.value,
            child: Text(item.label),
          ),
        )
        .toList(growable: false),
    onChanged: onChanged,
  );
}

const propertyTypes = <ChoiceItem>[
  ChoiceItem('apartment', 'آپارتمان'),
  ChoiceItem('house', 'خانه'),
  ChoiceItem('villa', 'ویلا'),
  ChoiceItem('land', 'زمین'),
  ChoiceItem('office', 'اداری'),
  ChoiceItem('commercial', 'تجاری'),
  ChoiceItem('warehouse', 'انبار'),
  ChoiceItem('other', 'سایر'),
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
  ChoiceItem('inactive', 'غیرفعال'),
  ChoiceItem('converted', 'تبدیل‌شده'),
  ChoiceItem('lost', 'از‌دست‌رفته'),
];
const customerIntents = <ChoiceItem>[
  ChoiceItem('buy', 'خرید'),
  ChoiceItem('rent', 'اجاره'),
];

String labelOf(List<ChoiceItem> items, String? value) =>
    items
        .where((item) => item.value == value)
        .map((item) => item.label)
        .firstOrNull ??
    value ??
    '—';
