# بررسی و بازیابی حادثه پایگاه داده محلی — ۲۰۲۶-۱۰-۰۳

**وضعیت نهایی:** بازیابی پایگاه داده محلی اصلی انجام و در `2026-10-03T20:33:53Z`، معادل ۰۰:۰۳:۵۳ روز ۴ اکتبر در Asia/Tehran، تأیید شد. بخش‌های اولیه زیر سابقه بررسی پیش از مجوز بازیابی هستند؛ نتیجه نهایی در انتهای گزارش آمده است. پایگاه داده تولید بررسی یا تغییر نکرده است.

این بررسی توسط عامل گفتگو/وب و فقط با خواندن وضعیت، شمارش رکوردها و کپی فایل‌های لاگ انجام شد. هیچ migration، reset، purge، restore یا اجرای تست پایگاه داده در این بررسی انجام نشد. داده تولید بررسی یا تغییر نکرده است.

## وضعیت مشاهده‌شده

در 18:15:57 UTC، اتصال برنامه محلی به `fandoogh` روی سرویس `db` بود؛ config cache فعال نبود. شمارش `agencies/users/properties/customers/owners/roles/permissions` و سایر جدول‌های دامنه صفر، `migrations` برابر ۲۷ و `sessions` برابر ۱ بود. زمان ایجاد جدول‌ها 18:11:14 تا 18:11:40 UTC است. این شمارش وضعیت پس از حادثه است و حجم داده قبل از حادثه را ثابت نمی‌کند.

طبق فرمان گزارش‌شده توسط عامل backend، PHPUnit روی `tests/Feature/Marketplace/MarketplaceAccessTest.php` با RefreshDatabase اجرا شده بود. migration گفتگوی جدید بر نام خودکار FK بیش از ۶۴ کاراکتر متوقف شد. نام صریح کوتاه `marketplace_read_conversation_fk` در کد جایگزین شده است.

## علت محیط تست

فرض اولیه config cache با شواهد مستقل رد شد: فایل cache وجود نداشت و bootstrap برنامه `cached=false` نشان داد. کد PHPUnit env force مقدار `getenv` و `$_ENV` را تغییر می‌دهد ولی `$_SERVER` را تغییر نمی‌دهد. در phpdotenv، ServerConstAdapter قبل از EnvConstAdapter و PutenvAdapter خوانده می‌شود. آزمایش بدون bootstrap یا اتصال DB نشان داد:

- getenv(DB_DATABASE): fandoogh_test
- $_ENV[DB_DATABASE]: fandoogh_test
- $_SERVER[DB_DATABASE]: fandoogh
- Illuminate Support Env::get(DB_DATABASE): fandoogh

در نتیجه، مقدار محیط Docker در SERVER بر تنظیم XML تقدم داشته است. bootstrap/runner تست باید هر سه منبع را قبل از ساخت برنامه همسان کند؛ افزون بر آن، هویت واقعی اتصال و نام اختصاصی تست باید قبل از RefreshDatabase بررسی شود.

## منابع بازیابی

فهرست ZIP بزرگ `D:/app/backups/fandoogh-pre-ui-20260828-2100.zip` (حدود ۱.۹۴ گیگابایت) و سه archive سورس در artifacts بدون استخراج کامل بررسی شد. هیچ SQL، SQL.GZ، dump، SQLite یا snapshot پایگاه داده در آن‌ها یافت نشد؛ این فایل‌ها مدرک بازیابی DB نیستند.

MySQL محلی log_bin=ON و binlog_format=ROW دارد؛ retention برابر 2592000 ثانیه (۳۰ روز) است. چهار لاگ به عنوان شاهد در `artifacts/recovery-20261003` کپی شدند؛ فایل اصلی دست‌نخورده است و پوشه خصوصی از Git ignore شده است. محتویات رکوردهای خصوصی چاپ نشده‌اند.

| فایل | اندازه کپی | اولین زمان UTC | آخرین زمان UTC |
|---|---:|---|---|
| binlog.000029 | 324469 | 2026-10-02 16:02:42 | 2026-10-02 16:25:04 |
| binlog.000030 | 1460167 | 2026-10-02 17:32:03 | 2026-10-02 20:39:42 |
| binlog.000031 | 350367 | 2026-10-03 13:41:23 | 2026-10-03 13:50:23 |
| binlog.000032 | 1029355 | 2026-10-03 17:34:56 | 2026-10-03 18:13:00 |

پارسر فقط header/query/table-map رویدادها را خواند. آخرین بازسازی کامل schema محلی پیش از حادثه در binlog.000030، 17:53:40 تا 17:54:02 UTC روز ۲ اکتبر ثبت شده است؛ CREATE migrations از position=109668 شروع می‌شود و همه ۲۵ جدول آن زمان وجود دارند. رویدادهای داده مربوط به seed نقش‌ها/مجوزها/آژانس/تنظیمات/کاربران و session بعد از این پایه دیده می‌شوند. وجود این رویدادها یک گزینه بازیابی است، تضمین کامل‌بودن بازیابی نیست.

**اولین DROP حادثه جاری** در binlog.000032، position=127117، زمان 18:08:13 UTC روز ۳ اکتبر است؛ شش DROP دیگر تا 18:11:13 رخ داده‌اند. بنابراین توقف بازپخش باید پیش از اولین DROP باشد، نه پیش از آخرین بازسازی مشاهده‌شده. در binlog.000031 هیچ DROP متعلق به fandoogh دیده نشد؛ DROPهای تست متعلق به fandoogh_test هستند.

## مرحله بعدی مجاز

این بخش، پیشنهاد تاریخی پیش از دریافت مجوز عامل ریشه است و با نتیجه نهایی زیر جایگزین شده است.

تا فرمان صریح عامل ریشه، هیچ بازپخش انجام نشده است. پیشنهاد: بازپخش استاندارد لاگ‌ها روی **container جدید و مستقل با volume جدید** از پایه schema کامل قبل حادثه، توقف در position=127117 فایل 000032، و مقایسه فقط شمارش/قیود/metadata. ابزار mysqlbinlog در container DB موجود نیست و در مسیرهای محلی dev/tools نیز یافت نشد؛ تهیه ابزار سازگار و تأیید replay لازم است. هیچ بازنویسی DB جاری، حذف/چرخاندن لاگ یا وعده بازیابی قطعی مجاز نشده است.

## نتیجه نهایی بازپخش و بازیابی محلی

با مجوز صریح عامل ریشه، بازپخش با ابزار رسمی MySQL 8.4.11 روی container مستقل `fandoogh-recovery-20261003` انجام شد؛ شبکه آن `none`، بدون port و volume آن مستقل بود. بازپخش از position=109668 فایل 000030 آغاز و پیش از position=127117 فایل 000032 متوقف شد. دامپ پیش از حادثه با SHA256 زیر حفظ شد:

`389C2A2C2685639E5A27775E2F4BA17D627A52C69691286A0F739F2DA380BCA0`

پیش از بازنویسی محلی، هویت container اصلی `6af52e1458f8`، پروژه محلی `fandoogh`، سرویس `db` و volume اصلی `fandoogh_mysql_data` تأیید شد. container بازیابی `77535e26f7b5` و volume مستقل آن با هدف اصلی متفاوت بودند. app و nginx خاموش ماندند. همه جدول‌های دامنه، کاربران، مجوزها و جدول‌های جدید موجود پیش از بازگردانی بررسی شدند؛ هیچ رکورد جدیدی وجود نداشت. جدول‌های migrations و sessions از شرط صفر بودن مستثنا و همراه کل schema پشتیبان‌گیری شدند.

نسخه کامل وضعیت پیش از بازگردانی در مسیر خصوصی و Git-ignored زیر باقی است:

`artifacts/recovery-20261003/main-before-verified-restore-14050711-202850.sql`

SHA256 نسخه وضعیت جاری:

`CD7723B023FEF0CB5CDC3AB93366EBC2D06267A3114E0C8D698E74A3FF561FC8`

تاریخ نام این فایل به علت فرهنگ تقویم میزبان شمسی است؛ زمان‌های نتیجه JSON به ISO UTC ثبت شده‌اند. پس از این snapshot و شرط نبود داده جدید، فقط schema صریح محلی `fandoogh` بازسازی و دامپ بازیابی‌شده وارد شد.

مقایسه نهایی با منبع مستقل، همه ۲۵ جدول، ۳۴۸ ستون، ۲۵ کلید اصلی، ۲۶ قید یکتا، ۵۲ check و ۳۸ کلید خارجی را تأیید کرد. default charset/collation پایگاه داده، نوع/ترتیب/nullability/default ستون‌ها، charset/collation مؤثر، indexها، checks، روابط و قواعد حذف/به‌روزرسانی و AUTO_INCREMENT همه برابر هستند. شمارش تک‌تک جدول‌ها و نمایش canonical همه ردیف‌های INSERT نیز دقیقاً برابر بود. تمام ۳۸ آزمون orphan نتیجه صفر داشتند.

| جدول | تعداد بازیابی‌شده |
|---|---:|
| agencies / agency_settings / users | هرکدام ۱ |
| roles | ۳ |
| permissions | ۵۱ |
| model_has_roles | ۱ |
| role_has_permissions | ۵۹ |
| sessions | ۷ |
| migrations | ۲۶ |
| سایر جدول‌های دامنه، از جمله properties / customers / owners | صفر |

صفر بودن داده‌های املاک، مشتریان و مالکان وضعیت واقعی پیش از حادثه در بازپخش تأییدشده است. مقایسه اولیه byte-for-byte دامپ، به علت اضافه‌شدن عبارت‌های صریح و تکراری charset/collation در نمایش DDL هدف متفاوت بود؛ مقایسه metadata مؤثر و همه ردیف‌ها برابری کامل را ثابت کرد. SHA256 نمایش canonical ردیف‌ها:

`6AAB9DCB3201C286D29508E62C0C354BBFB91402FA68C31691D433E4A3815210`

شواهد خصوصی، دامپ اولیه، snapshot، دامپ‌های مقایسه و نتیجه sanitised در `artifacts/recovery-20261003/verified-local-restore-result.json` نگهداری شده‌اند؛ هیچ SQL خام یا اطلاعات خصوصی وارد Git یا خروجی عمومی نشده است. اسکریپت‌های incident-specific در `scripts/recovery/Restore-VerifiedLocalDatabase.ps1` و `Verify-RestoredLocalDatabase.ps1` کنترل هویت، guard داده جدید، snapshot و مقایسه را ثبت می‌کنند. اجرای دوباره restore روی baseline بازیابی‌شده به علت وجود رکورد دامنه متوقف می‌شود.

در زمان تحویل baseline به عامل backend، app و nginx خاموش بودند. راه‌اندازی app و اجرای migration/seed افزایشی بعدی به عامل backend واگذار شد؛ این نتیجه، baseline پیش از آن تغییرهای مجاز است. هیچ migrate:fresh یا تست RefreshDatabase روی DB اصلی پس از بازیابی اجرا نشد.

## تعمیر برگشت‌پذیر runtime محلی Docker

Docker Desktop 4.83.0 هنگام شروع به دلیل AF_UNIX reparse-point خراب در `dockerInference` و سپس `engine.sock` متوقف می‌شد. این الگو در [گزارش رسمی tracker Docker شماره 554](https://github.com/docker/desktop-feedback/issues/554) ثبت شده است. حذف، کپی و تغییر نام خود socket با خطای سیستم 1920 ناممکن بود؛ هیچ فایل شاهد یا volume حذف نشد.

پس از توقف فقط Docker Desktop، مسیرهای دقیق socket-only زیر با rename در همان محل حفظ و پوشه runtime خالی ساخته شد:

- `C:/Users/Dolphin/AppData/Local/Docker/run.stale-recovery-20261003`
- `C:/Users/Dolphin/AppData/Local/Docker/run.stale-recovery-20261003-second`
- `C:/Users/Dolphin/AppData/Local/docker-secrets-engine.stale-recovery-20261003`

تلاش اول پس از تعمیر inference در مرحله secrets متوقف شد و socket جدید inference باقی گذاشت؛ تلاش نهایی پس از تازه‌بودن هم‌زمان هر دو مسیر موفق شد. سپس فقط دو container شناخته‌شده DB اصلی و بازیابی آغاز شدند. factory reset، reinstall/update، تغییر تنظیمات Docker، global WSL shutdown، حذف VHD/volume و پاک‌سازی بازگشتی انجام نشد. پوشه‌های socket قدیمی برای برگشت‌پذیری باقی هستند.

## کنترل نهایی runtime در ۲۰۲۶-۱۰-۰۴

در شروع کنترل نهایی، Docker Desktop و engine خاموش و endpoint محلی در دسترس نبودند. شروع عادی دوباره همان خطای socket `dockerInference` را نشان داد. فقط دو parent دقیق `C:/Users/Dolphin/AppData/Local/Docker/run` و `C:/Users/Dolphin/AppData/Local/docker-secrets-engine` بررسی شدند؛ تمام اعضا فایل صفر‌بایتی ReparsePoint بودند و هیچ پوشه یا فایل داده‌ای نداشتند. با engine خاموش، این دو parent به پسوند `.stale-recovery-20261004-final` در همان محل تغییر نام یافتند و نسخه قبلی حفظ شد. پوشه خالی runtime ساخته و Docker Desktop با پنجره مخفی شروع شد.

پس از آماده‌شدن engine، فقط سرویس‌های موجود db، app و nginx آغاز شدند. DB healthy و PHP 8.3.32 تأیید شدند. volume بازیابی‌شده باقی بود؛ هیچ بازپخش دوباره، reset، reinstall، پاک‌کردن volume یا WSL shutdown انجام نشد. schema محلی پس از تغییرهای افزایشی مجاز، ۳۴ جدول و ۲۸ مهاجرت دارد؛ شمارش agency/user/settings هرکدام ۱، roles=۳، permissions=۵۳، role grants=۶۱، جغرافیا=۳۱/۴۸۴/۱۴۸۱ و جدول‌های دامنه و بازار خالی تأیید شدند. sessions=۸ شامل نشست جدید حاصل از کنترل HTTP قبلی است؛ این تفاوت به‌عنوان بازیابی ناقص معرفی نمی‌شود.
