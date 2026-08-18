import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/customers/presentation/customer_form_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customer_detail_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customers_page.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/properties/presentation/property_detail_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/properties_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/property_form_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/widgets/property_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('villa and industrial choices are available on mobile forms', () {
    expect(propertyTypes.map((item) => item.value), contains('industrial'));
    expect(buildingTypes.map((item) => item.value), <String>[
      'detached',
      'township',
      'duplex',
      'triplex',
      'apartment_villa',
    ]);
  });

  test(
    'sale delivery choices include occupancy and exclude legacy dated option',
    () {
      expect(saleDeliveryStatuses.map((item) => item.value), <String>[
        'owner_occupied',
        'tenant_occupied',
        'ready',
        'vacated',
      ]);
      expect(
        rentalDeliveryStatuses.map((item) => item.value),
        contains('dated'),
      );
    },
  );

  testWidgets('property path renders server results and primary action', (
    tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          propertyRepositoryProvider.overrideWithValue(
            _FakePropertyRepository(),
          ),
        ],
        child: const MaterialApp(home: PropertiesPage()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('املاک'), findsOneWidget);
    expect(find.text('آپارتمان سعادت‌آباد'), findsOneWidget);
    expect(find.text('ملک جدید'), findsOneWidget);
    expect(find.byType(SearchBar), findsOneWidget);
    expect(find.byTooltip('مرتب‌سازی'), findsOneWidget);
    expect(find.byTooltip('فیلترها'), findsOneWidget);
  });

  testWidgets('property transaction shortcuts filter the list', (tester) async {
    final repository = _FakePropertyRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [propertyRepositoryProvider.overrideWithValue(repository)],
        child: const MaterialApp(home: PropertiesPage()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('اجاره'));
    await tester.pumpAndSettle();
    expect(repository.lastFilters['transaction_type'], 'rent');

    await tester.tap(find.text('همه'));
    await tester.pumpAndSettle();
    expect(repository.lastFilters['transaction_type'], isNull);
  });

  testWidgets('property card shows the approved summary fields', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PropertyCard(
            title: 'خانهٔ نمونه',
            transactionType: 'rent',
            area: 120,
            salePrice: null,
            depositAmount: 100000000,
            monthlyRent: 1500000,
            parkingSpaces: 1,
            ownerName: 'مالک نمونه',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('خانهٔ نمونه'), findsOneWidget);
    expect(find.text('اجاره'), findsOneWidget);
    expect(_richTextContaining('متراژ'), findsOneWidget);
    expect(_richTextContaining('ودیعه'), findsOneWidget);
    expect(_richTextContaining('پارکینگ'), findsOneWidget);
    expect(_richTextContaining('مالک'), findsOneWidget);
    expect(find.text('کد ملک'), findsNothing);
  });

  testWidgets('property card uses the cover image as a square preview', (
    tester,
  ) async {
    const coverUrl =
        'https://crm.fandooghstudio.ir/api/v1/properties/1/images/2/content';
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PropertyCard.fromMap(<String, dynamic>{
            'title': 'خانهٔ کاوردار',
            'transaction_type': 'sale',
            'area_sqm': 100,
            'images': <Map<String, dynamic>>[
              <String, dynamic>{
                'is_cover': false,
                'content_url': 'https://example.com/old.jpg',
              },
              <String, dynamic>{'is_cover': true, 'content_url': coverUrl},
            ],
          }),
        ),
      ),
    );
    await tester.pump();

    final image = tester.widget<Image>(find.byType(Image));
    expect((image.image as NetworkImage).url, coverUrl);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is SizedBox && widget.width == 144 && widget.height == 144,
      ),
      findsOneWidget,
    );
  });

  testWidgets('property card renders nested dashboard property data', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: PropertyCard.fromMap(<String, dynamic>{
            'data': <String, dynamic>{
              'title': 'ملک صفحه خانه',
              'transaction_type': 'sale',
              'area_sqm': 140,
              'sale_price': 2000000000,
            },
          }),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('ملک صفحه خانه'), findsOneWidget);
    expect(_richTextContaining('متراژ'), findsOneWidget);
    expect(find.text('مبلغ کل'), findsOneWidget);
  });

  testWidgets('property filters can be removed and sorting stays outside', (
    tester,
  ) async {
    final repository = _FakePropertyRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [propertyRepositoryProvider.overrideWithValue(repository)],
        child: const MaterialApp(home: PropertiesPage()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('فیلترها'));
    await tester.pumpAndSettle();
    expect(find.text('مرتب‌سازی'), findsNothing);
    await tester.tap(find.byType(ChoiceField).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('موجود').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('اعمال'));
    await tester.pumpAndSettle();

    expect(find.text('حذف فیلترها'), findsOneWidget);
    expect(repository.lastFilters['status'], 'available');
    await tester.tap(find.text('حذف فیلترها'));
    await tester.pumpAndSettle();
    expect(repository.lastFilters['status'], isNull);
  });

  testWidgets('property thumbnail opens a full screen preview', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          propertyRepositoryProvider.overrideWithValue(
            _FakePropertyRepository(),
          ),
        ],
        child: const MaterialApp(home: PropertyDetailPage(propertyId: 1)),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('تصاویر'));
    await tester.pumpAndSettle();
    expect(find.byType(PageView), findsNWidgets(2));
    expect(
      find.byKey(const ValueKey('property-image-indicator-0')),
      findsOneWidget,
    );
    expect(
      find.byKey(const ValueKey('property-image-indicator-1')),
      findsOneWidget,
    );
    await tester.tap(find.bySemanticsLabel('نمایش تصویر کامل').first);
    await tester.pumpAndSettle();

    expect(find.text('تصویر ملک'), findsOneWidget);
    expect(find.byType(InteractiveViewer), findsOneWidget);
  });

  testWidgets(
    'customer path renders server results at 200 percent text scale',
    (tester) async {
      await tester.pumpWidget(
        MediaQuery(
          data: const MediaQueryData(textScaler: TextScaler.linear(2)),
          child: ProviderScope(
            overrides: [
              customerRepositoryProvider.overrideWithValue(
                _FakeCustomerRepository(),
              ),
            ],
            child: const MaterialApp(home: CustomersPage()),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('مشتریان'), findsOneWidget);
      expect(find.text('نیما مرادی'), findsOneWidget);
      expect(find.text('مشتری جدید'), findsOneWidget);
      expect(find.byTooltip('مرتب‌سازی'), findsOneWidget);
      expect(find.byTooltip('فیلترها'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('customer filters can be removed and sorting stays outside', (
    tester,
  ) async {
    final repository = _FakeCustomerRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [customerRepositoryProvider.overrideWithValue(repository)],
        child: const MaterialApp(home: CustomersPage()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byTooltip('فیلترها'));
    await tester.pumpAndSettle();
    expect(find.text('مرتب‌سازی'), findsNothing);
    await tester.tap(find.byType(ChoiceField).first);
    await tester.pumpAndSettle();
    await tester.tap(find.text('فعال').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('اعمال'));
    await tester.pumpAndSettle();

    expect(find.text('حذف فیلترها'), findsOneWidget);
    expect(repository.lastFilters['status'], 'active');
    await tester.tap(find.text('حذف فیلترها'));
    await tester.pumpAndSettle();
    expect(repository.lastFilters['status'], isNull);
  });

  testWidgets('property create path exposes the four-step workflow', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: PropertyFormPage())),
    );
    expect(find.text('ثبت ملک جدید'), findsOneWidget);
    expect(find.text('اطلاعات اصلی'), findsOneWidget);
    expect(find.text('عنوان ملک'), findsOneWidget);
    expect(find.text('مرحله بعد'), findsOneWidget);
    expect(find.byType(LinearProgressIndicator), findsOneWidget);
  });

  testWidgets('property steps navigate as pages and keep entered values', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: PropertyFormPage())),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'ملک آزمایشی');
    await tester.tap(find.text('مرحله بعد'));
    await tester.pumpAndSettle();

    expect(find.text('مبلغ و نشانی'), findsOneWidget);
    expect(find.text('عنوان ملک'), findsNothing);

    await tester.tap(find.text('مرحله قبل'));
    await tester.pumpAndSettle();
    expect(find.text('عنوان ملک'), findsOneWidget);
    expect(find.text('ملک آزمایشی'), findsOneWidget);
  });

  testWidgets('property image step exposes thumbnail management controls', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: PropertyFormPage())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('مالک و تأیید'));
    await tester.pumpAndSettle();

    expect(find.text('تصاویر ملک'), findsOneWidget);
    expect(find.text('هنوز تصویری انتخاب نشده است.'), findsOneWidget);
    expect(find.text('افزودن تصویر'), findsOneWidget);
  });

  testWidgets('property form exposes approved shared feature controls', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: PropertyFormPage())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('مشخصات ساختمان'));
    await tester.pumpAndSettle();

    expect(find.text('نوع سرویس'), findsOneWidget);
    expect(find.text('سرویس مستر'), findsOneWidget);
    expect(find.text('نوع کابینت'), findsOneWidget);
    expect(find.text('نوع گرمایش'), findsOneWidget);
    expect(find.text('نوع سرمایش'), findsOneWidget);
    expect(find.text('نوع کف‌پوش'), findsOneWidget);
    expect(find.text('وضعیت بازسازی'), findsOneWidget);
    expect(find.text('جهت ساختمان'), findsOneWidget);
    expect(find.text('نوع سند'), findsOneWidget);
    expect(find.byType(CheckboxListTile), findsNWidgets(2));
    expect(find.byType(ChoiceChip), findsNWidgets(13));
  });

  testWidgets('owner contact numbers expose call buttons', (tester) async {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          propertyRepositoryProvider.overrideWithValue(
            _FakePropertyRepository(),
          ),
        ],
        child: const MaterialApp(home: PropertyDetailPage(propertyId: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byTooltip('تماس با همراه مالک'), findsOneWidget);
    expect(find.byTooltip('تماس با تلفن ثابت مالک'), findsOneWidget);
  });

  testWidgets('customer contact numbers expose call buttons', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          customerRepositoryProvider.overrideWithValue(
            _FakeCustomerRepository(),
          ),
        ],
        child: const MaterialApp(home: CustomerDetailPage(customerId: 1)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byTooltip('تماس با موبایل مشتری'), findsOneWidget);
    expect(find.byTooltip('تماس با تلفن ثابت مشتری'), findsOneWidget);
  });

  testWidgets('customer create path exposes the five-step workflow', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: CustomerFormPage())),
    );
    expect(find.text('ثبت مشتری جدید'), findsOneWidget);
    expect(find.byType(Stepper), findsNothing);
    expect(find.byType(LinearProgressIndicator), findsOneWidget);
    expect(find.text('اطلاعات تماس'), findsOneWidget);
    expect(find.text('نام کامل مشتری'), findsOneWidget);
  });

  testWidgets('customer steps navigate as pages and keep entered values', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: CustomerFormPage())),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextFormField).first, 'مشتری آزمایشی');
    await tester.tap(find.text('مرحله بعد'));
    await tester.pumpAndSettle();

    expect(find.text('نوع نیاز'), findsOneWidget);
    expect(find.text('نام کامل مشتری'), findsNothing);

    await tester.tap(find.text('مرحله قبل'));
    await tester.pumpAndSettle();
    expect(find.text('اطلاعات تماس'), findsOneWidget);
    expect(find.text('مشتری آزمایشی'), findsOneWidget);
  });

  testWidgets('customer form exposes approved shared feature controls', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1080, 4000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: CustomerFormPage())),
    );
    await tester.tap(find.text('ویژگی‌های موردنظر'));
    await tester.pumpAndSettle();

    expect(find.text('نوع سرویس'), findsOneWidget);
    expect(find.text('سرویس مستر'), findsOneWidget);
    expect(find.text('نوع کابینت'), findsOneWidget);
    expect(find.text('نوع گرمایش'), findsOneWidget);
    expect(find.text('نوع سرمایش'), findsOneWidget);
    expect(find.text('نوع کف‌پوش'), findsOneWidget);
    expect(find.text('وضعیت بازسازی'), findsOneWidget);
    expect(find.text('جهت ساختمان'), findsOneWidget);
    expect(find.text('نوع سند'), findsOneWidget);
    expect(find.byType(CheckboxListTile), findsNWidgets(2));
    expect(find.byType(ChoiceChip), findsNWidgets(13));
  });
}

Finder _richTextContaining(String value) => find.byWidgetPredicate((widget) {
  return widget is RichText && widget.text.toPlainText().contains(value);
});

final class _FakePropertyRepository extends PropertyRepository {
  _FakePropertyRepository() : super(ApiClient());

  Map<String, Object?> lastFilters = const <String, Object?>{};

  @override
  Future<PagedResult<PropertyRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async {
    lastFilters = filters;
    return PagedResult<PropertyRecord>(
      items: <PropertyRecord>[_property],
      currentPage: 1,
      lastPage: 1,
    );
  }

  @override
  Future<PropertyRecord> find(int id) async => _property;

  @override
  Future<List<Map<String, dynamic>>> images(int id) async =>
      <Map<String, dynamic>>[
        <String, dynamic>{
          'id': 10,
          'content_url': 'https://example.test/property.jpg',
          'is_cover': true,
        },
        <String, dynamic>{
          'id': 11,
          'content_url': 'https://example.test/property-2.jpg',
          'is_cover': false,
        },
      ];

  @override
  Future<List<Map<String, dynamic>>> notes(int id) async =>
      <Map<String, dynamic>>[];

  @override
  Future<List<Map<String, dynamic>>> history(int id) async =>
      <Map<String, dynamic>>[];

  static final PropertyRecord _property = PropertyRecord.fromJson(
    <String, dynamic>{
      'id': 1,
      'title': 'آپارتمان سعادت‌آباد',
      'code': 'PR-0001',
      'status': 'available',
      'property_type': 'apartment',
      'transaction_type': 'sale',
      'city': 'تهران',
      'toilet_types': <String>['iranian', 'western'],
      'has_master_bathroom': true,
      'heating_type': 'floor_heating',
      'cooling_type': 'duct_split',
      'owners': <Map<String, dynamic>>[
        <String, dynamic>{
          'full_name': 'مالک نمونه',
          'mobile': '09120000001',
          'phone': '02112345678',
        },
      ],
    },
  );
}

final class _FakeCustomerRepository extends CustomerRepository {
  _FakeCustomerRepository() : super(ApiClient());

  Map<String, Object?> lastFilters = const <String, Object?>{};

  @override
  Future<CustomerRecord> find(int id) async => _customer;

  @override
  Future<PagedResult<CustomerRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async {
    lastFilters = filters;
    return PagedResult<CustomerRecord>(
      items: <CustomerRecord>[_customer],
      currentPage: 1,
      lastPage: 1,
    );
  }

  static final CustomerRecord _customer =
      CustomerRecord.fromJson(<String, dynamic>{
        'id': 1,
        'first_name': 'نیما',
        'last_name': 'مرادی',
        'mobile': '+989121234567',
        'phone': '02112345678',
        'status': 'active',
        'intent': 'buy',
      });
}
