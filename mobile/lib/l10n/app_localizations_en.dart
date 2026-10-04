// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appName => 'دفتر املاکی';

  @override
  String get foundationTitle => 'زیرساخت اولیه آماده است';

  @override
  String get foundationDescription =>
      'سامانه برای اجرای فرایندهای دفتر املاکی آماده است.';

  @override
  String get foundationStatus => 'آماده';

  @override
  String get foundationIconLabel => 'نشان سامانه دفتر املاکی';
}
