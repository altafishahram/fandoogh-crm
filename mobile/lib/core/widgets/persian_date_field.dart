import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:flutter/material.dart';

final class PersianDateField extends StatelessWidget {
  const PersianDateField({
    required this.label,
    required this.value,
    required this.onChanged,
    this.firstYear = 1300,
    this.lastYear = 1500,
    super.key,
  });

  final String label;
  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final int firstYear;
  final int lastYear;

  @override
  Widget build(BuildContext context) => InkWell(
    borderRadius: BorderRadius.circular(16),
    onTap: () async {
      final selected = await showPersianDatePicker(
        context,
        initialDate: value,
        firstYear: firstYear,
        lastYear: lastYear,
      );
      if (selected != null) onChanged(selected);
    },
    child: InputDecorator(
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: const Icon(Icons.calendar_month_outlined),
      ),
      child: Text(
        value == null ? 'انتخاب تاریخ' : PersianDate.format(value),
        style: TextStyle(
          color: value == null
              ? Theme.of(context).colorScheme.onSurfaceVariant
              : Theme.of(context).colorScheme.onSurface,
        ),
      ),
    ),
  );
}

Future<DateTime?> showPersianDatePicker(
  BuildContext context, {
  DateTime? initialDate,
  int firstYear = 1300,
  int lastYear = 1500,
}) {
  final initial = PersianDate.fromGregorian(initialDate ?? DateTime.now());
  var year = initial.year.clamp(firstYear, lastYear);
  var month = initial.month;
  var day = initial.day;

  return showModalBottomSheet<DateTime>(
    context: context,
    isScrollControlled: true,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) {
        final days = PersianDate.daysInMonth(year, month);
        if (day > days) day = days;
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                Text(
                  'انتخاب تاریخ شمسی',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 20),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: ChoiceField(
                        value: '$day',
                        label: 'روز',
                        items: List<ChoiceItem>.generate(
                          days,
                          (index) => ChoiceItem(
                            '${index + 1}',
                            persianDigits(index + 1),
                          ),
                        ),
                        onChanged: (value) => setState(
                          () => day = int.tryParse(value ?? '') ?? day,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      flex: 2,
                      child: ChoiceField(
                        value: '$month',
                        label: 'ماه',
                        items: List<ChoiceItem>.generate(
                          12,
                          (index) => ChoiceItem(
                            '${index + 1}',
                            PersianDate.monthNames[index],
                          ),
                        ),
                        onChanged: (value) => setState(
                          () => month = int.tryParse(value ?? '') ?? month,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ChoiceField(
                        value: '$year',
                        label: 'سال',
                        items: <ChoiceItem>[
                          for (
                            var value = lastYear;
                            value >= firstYear;
                            value--
                          )
                            ChoiceItem('$value', persianDigits(value)),
                        ],
                        onChanged: (value) => setState(
                          () => year = int.tryParse(value ?? '') ?? year,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => Navigator.pop(
                    context,
                    PersianDate(year, month, day).toGregorian(),
                  ),
                  child: Text('تأیید ${PersianDate(year, month, day).display}'),
                ),
              ],
            ),
          ),
        );
      },
    ),
  );
}
