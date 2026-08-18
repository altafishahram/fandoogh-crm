import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

const _persianDigits = '۰۱۲۳۴۵۶۷۸۹';
const _arabicDigits = '٠١٢٣٤٥٦٧٨٩';
const _latinDigits = '0123456789';

/// Converts the digits and separators used in the UI to a plain API number.
///
/// The returned value never contains thousands separators and uses `.` as the
/// decimal separator so it can safely be passed to the API or parsed by Dart.
String normalizeNumericText(String value) {
  final result = StringBuffer();
  var hasDecimal = false;
  var hasSign = false;

  for (final character in value.trim().split('')) {
    final persianIndex = _persianDigits.indexOf(character);
    final arabicIndex = _arabicDigits.indexOf(character);
    final digitIndex = persianIndex >= 0 ? persianIndex : arabicIndex;
    if (digitIndex >= 0) {
      result.write(_latinDigits[digitIndex]);
      continue;
    }

    if (_latinDigits.contains(character)) {
      result.write(character);
      continue;
    }

    if ((character == '.' || character == '٫') && !hasDecimal) {
      result.write('.');
      hasDecimal = true;
      continue;
    }

    if (character == '-' && result.isEmpty && !hasSign) {
      result.write('-');
      hasSign = true;
    }
    // Commas, Arabic thousands separators and spaces are presentation-only.
  }

  return result.toString();
}

/// Formats a number for an editable Persian form field.
///
/// Digits are Persian and the Arabic thousands separator (`٬`) is inserted
/// between groups. Decimal input is kept for measurements, while integer
/// fields discard the fractional part.
String formatPersianNumberInput(String value, {bool integer = false}) {
  final normalized = normalizeNumericText(value);
  if (normalized.isEmpty) return '';

  final negative = normalized.startsWith('-');
  final unsigned = negative ? normalized.substring(1) : normalized;
  final decimalIndex = unsigned.indexOf('.');
  var integerPart = decimalIndex < 0
      ? unsigned
      : unsigned.substring(0, decimalIndex);
  final decimalPart = decimalIndex < 0
      ? ''
      : unsigned.substring(decimalIndex + 1);

  if (integerPart.isEmpty) integerPart = '0';
  final grouped = _groupThousands(integerPart);
  final decimal = !integer && decimalIndex >= 0 ? '٫$decimalPart' : '';
  final formatted = '${negative ? '-' : ''}$grouped$decimal';
  return _toPersianDigits(formatted);
}

String _groupThousands(String value) {
  final groups = <String>[];
  var end = value.length;
  while (end > 3) {
    groups.insert(0, value.substring(end - 3, end));
    end -= 3;
  }
  groups.insert(0, value.substring(0, end));
  return groups.join('٬');
}

String _toPersianDigits(String value) {
  return value.split('').map((character) {
    final index = _latinDigits.indexOf(character);
    return index < 0 ? character : _persianDigits[index];
  }).join();
}

String _toPersianDigitsWithoutGrouping(String value) {
  return value.split('').map((character) {
    final persianIndex = _persianDigits.indexOf(character);
    if (persianIndex >= 0) return _persianDigits[persianIndex];
    final arabicIndex = _arabicDigits.indexOf(character);
    if (arabicIndex >= 0) return _persianDigits[arabicIndex];
    final latinIndex = _latinDigits.indexOf(character);
    return latinIndex >= 0 ? _persianDigits[latinIndex] : character;
  }).join();
}

String formatPersianDigits(Object? value) =>
    value == null ? '' : _toPersianDigitsWithoutGrouping('$value');

TextEditingValue _withFormattedCursor(
  TextEditingValue oldValue,
  TextEditingValue newValue,
  String Function(String value) formatter,
) {
  final offset = newValue.selection.baseOffset < 0
      ? newValue.text.length
      : newValue.selection.baseOffset.clamp(0, newValue.text.length);
  final formatted = formatter(newValue.text);
  final formattedBeforeCursor = formatter(newValue.text.substring(0, offset));
  final cursor = formattedBeforeCursor.length.clamp(0, formatted.length);
  return newValue.copyWith(
    text: formatted,
    selection: TextSelection.collapsed(offset: cursor),
    composing: TextRange.empty,
  );
}

/// Displays editable numeric values with Persian digits and grouping.
final class PersianNumberTextInputFormatter extends TextInputFormatter {
  const PersianNumberTextInputFormatter({this.integer = false});

  final bool integer;

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    return _withFormattedCursor(
      oldValue,
      newValue,
      (value) => formatPersianNumberInput(value, integer: integer),
    );
  }
}

/// Displays Persian digits for phone numbers without applying numeric grouping.
final class PersianDigitsTextInputFormatter extends TextInputFormatter {
  const PersianDigitsTextInputFormatter();

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    return _withFormattedCursor(
      oldValue,
      newValue,
      _toPersianDigitsWithoutGrouping,
    );
  }
}

String? amountInWords(Object? value) {
  if (value == null) return null;
  final normalized = normalizeNumericText('$value');
  if (normalized.isEmpty) return null;
  final number = num.tryParse(normalized);
  if (number == null || !number.isFinite) return null;
  return _persianIntegerToWords(number.round());
}

String _persianIntegerToWords(int value) {
  if (value == 0) return 'صفر';
  if (value < 0) return 'منفی ${_persianIntegerToWords(-value)}';

  const scales = <String>[
    '',
    'هزار',
    'میلیون',
    'میلیارد',
    'تریلیون',
    'کوادریلیون',
    'کوینتیلیون',
  ];
  var remaining = value;
  var scaleIndex = 0;
  final parts = <String>[];
  while (remaining > 0) {
    final group = remaining % 1000;
    if (group != 0) {
      final words = _threeDigitsToWords(group);
      final scale = scaleIndex < scales.length ? scales[scaleIndex] : '';
      parts.insert(0, scale.isEmpty ? words : '$words $scale');
    }
    remaining ~/= 1000;
    scaleIndex++;
  }
  return parts.join(' و ');
}

String _threeDigitsToWords(int value) {
  const ones = <String>[
    'صفر',
    'یک',
    'دو',
    'سه',
    'چهار',
    'پنج',
    'شش',
    'هفت',
    'هشت',
    'نه',
    'ده',
    'یازده',
    'دوازده',
    'سیزده',
    'چهارده',
    'پانزده',
    'شانزده',
    'هفده',
    'هجده',
    'نوزده',
  ];
  const tens = <String>[
    '',
    '',
    'بیست',
    'سی',
    'چهل',
    'پنجاه',
    'شصت',
    'هفتاد',
    'هشتاد',
    'نود',
  ];
  const hundreds = <String>[
    '',
    'صد',
    'دویست',
    'سیصد',
    'چهارصد',
    'پانصد',
    'ششصد',
    'هفتصد',
    'هشتصد',
    'نهصد',
  ];

  final parts = <String>[];
  var remainder = value;
  final hundred = remainder ~/ 100;
  if (hundred > 0) {
    parts.add(hundreds[hundred]);
    remainder %= 100;
  }
  if (remainder >= 20) {
    parts.add(tens[remainder ~/ 10]);
    remainder %= 10;
    if (remainder > 0) parts.add(ones[remainder]);
  } else if (remainder > 0) {
    parts.add(ones[remainder]);
  }
  return parts.join(' و ');
}

/// A compact textual representation of a monetary value below an input.
final class AmountInWordsHint extends StatelessWidget {
  const AmountInWordsHint({required this.controller, super.key});

  final TextEditingController controller;

  @override
  Widget build(BuildContext context) {
    return ValueListenableBuilder<TextEditingValue>(
      valueListenable: controller,
      builder: (context, value, child) {
        final words = amountInWords(value.text);
        if (words == null) return const SizedBox.shrink();
        return Align(
          alignment: AlignmentDirectional.centerStart,
          child: Padding(
            padding: const EdgeInsetsDirectional.only(
              start: 12,
              top: 3,
              bottom: 2,
            ),
            child: Text(
              'به حروف: $words تومان',
              style: TextStyle(
                fontSize: 11,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
            ),
          ),
        );
      },
    );
  }
}
