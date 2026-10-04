import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('formats and converts the approved Persian date', () {
    final date = PersianDate.fromGregorian(DateTime(2026, 8, 8));

    expect(date.display, '۱۷ مرداد ۱۴۰۵');
    expect(date.isoDate, '2026-08-08');
  });

  test('handles the last day of Persian year 1403', () {
    final date = PersianDate.fromGregorian(DateTime(2025, 3, 20));

    expect(date.display, '۳۰ اسفند ۱۴۰۳');
    expect(date.toGregorian(), DateTime(2025, 3, 20));
  });

  test('formats toman without decimals and with Persian digits', () {
    expect(formatToman('1250000.4'), '۱٬۲۵۰٬۰۰۰ تومان');
    expect(formatToman('1250000.6'), '۱٬۲۵۰٬۰۰۱ تومان');
  });
}
