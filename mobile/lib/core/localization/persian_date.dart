import 'package:intl/intl.dart';

final class PersianDate {
  const PersianDate(this.year, this.month, this.day);

  final int year;
  final int month;
  final int day;

  static const monthNames = <String>[
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
  ];

  factory PersianDate.fromGregorian(DateTime value) {
    final gy = value.year;
    final gm = value.month;
    final gd = value.day;
    const monthDays = <int>[
      0,
      31,
      59,
      90,
      120,
      151,
      181,
      212,
      243,
      273,
      304,
      334,
    ];
    final adjustedYear = gm > 2 ? gy + 1 : gy;
    var days =
        355666 +
        (365 * gy) +
        ((adjustedYear + 3) ~/ 4) -
        ((adjustedYear + 99) ~/ 100) +
        ((adjustedYear + 399) ~/ 400) +
        gd +
        monthDays[gm - 1];
    var jy = -1595 + (33 * (days ~/ 12053));
    days %= 12053;
    jy += 4 * (days ~/ 1461);
    days %= 1461;
    if (days > 365) {
      jy += (days - 1) ~/ 365;
      days = (days - 1) % 365;
    }
    final jm = days < 186 ? 1 + (days ~/ 31) : 7 + ((days - 186) ~/ 30);
    final jd = 1 + (days < 186 ? days % 31 : (days - 186) % 30);
    return PersianDate(jy, jm, jd);
  }

  DateTime toGregorian() {
    var jy = year + 1595;
    var days =
        -355668 +
        (365 * jy) +
        ((jy ~/ 33) * 8) +
        (((jy % 33) + 3) ~/ 4) +
        day +
        (month < 7 ? (month - 1) * 31 : ((month - 7) * 30) + 186);
    var gy = 400 * (days ~/ 146097);
    days %= 146097;
    if (days > 36524) {
      gy += 100 * (--days ~/ 36524);
      days %= 36524;
      if (days >= 365) days++;
    }
    gy += 4 * (days ~/ 1461);
    days %= 1461;
    if (days > 365) {
      gy += (days - 1) ~/ 365;
      days = (days - 1) % 365;
    }
    var gd = days + 1;
    const gregorianMonthDays = <int>[
      31,
      28,
      31,
      30,
      31,
      30,
      31,
      31,
      30,
      31,
      30,
      31,
    ];
    var gm = 0;
    while (gm < 12) {
      final monthLength =
          gregorianMonthDays[gm] + (gm == 1 && _isGregorianLeap(gy) ? 1 : 0);
      if (gd <= monthLength) break;
      gd -= monthLength;
      gm++;
    }
    return DateTime(gy, gm + 1, gd);
  }

  String get display =>
      '${persianDigits(day)} ${monthNames[month - 1]} ${persianDigits(year)}';

  String get isoDate => DateFormat('yyyy-MM-dd').format(toGregorian());

  static String format(DateTime? value) =>
      value == null ? '—' : PersianDate.fromGregorian(value).display;

  static String formatIso(String? value) {
    final parsed = value == null ? null : DateTime.tryParse(value);
    return format(parsed);
  }

  static int daysInMonth(int year, int month) {
    if (month <= 6) return 31;
    if (month <= 11) return 30;
    return _isJalaliLeap(year) ? 30 : 29;
  }

  static bool _isGregorianLeap(int year) =>
      year % 400 == 0 || (year % 4 == 0 && year % 100 != 0);

  static bool _isJalaliLeap(int year) {
    final start = PersianDate(year, 1, 1).toGregorian();
    final next = PersianDate(year + 1, 1, 1).toGregorian();
    return next.difference(start).inDays == 366;
  }
}

String persianDigits(Object value) {
  const latin = '0123456789';
  const persian = '۰۱۲۳۴۵۶۷۸۹';
  return value.toString().split('').map((character) {
    final index = latin.indexOf(character);
    return index < 0 ? character : persian[index];
  }).join();
}

String latinDigits(String value) {
  const latin = '0123456789';
  const persian = '۰۱۲۳۴۵۶۷۸۹';
  return value.split('').map((character) {
    final index = persian.indexOf(character);
    return index < 0 ? character : latin[index];
  }).join();
}

String formatToman(Object? value) {
  if (value == null || '$value'.isEmpty) return '—';
  final number = num.tryParse(latinDigits('$value'));
  if (number == null) return '$value تومان';
  final grouped = NumberFormat.decimalPattern(
    'en',
  ).format(number.round()).replaceAll(',', '٬');
  return '${persianDigits(grouped)} تومان';
}
