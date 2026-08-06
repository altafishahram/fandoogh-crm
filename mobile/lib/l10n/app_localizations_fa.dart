// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Persian (`fa`).
class AppLocalizationsFa extends AppLocalizations {
  AppLocalizationsFa([String locale = 'fa']) : super(locale);

  @override
  String get appName => 'ملک بان';

  @override
  String get foundationTitle => 'زیرساخت اولیه آماده است';

  @override
  String get foundationDescription =>
      'احراز هویت و فرایندهای کسب‌وکار فقط پس از تأیید فاز بعدی اضافه می‌شوند.';

  @override
  String get foundationStatus => 'فاز صفر';

  @override
  String get foundationIconLabel => 'نشان سامانه ملک بان';
}
