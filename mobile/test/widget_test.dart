import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:fandoogh_crm/features/customers/presentation/customer_form_page.dart';
import 'package:fandoogh_crm/features/customers/presentation/customers_page.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/properties/presentation/properties_page.dart';
import 'package:fandoogh_crm/features/properties/presentation/property_form_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
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

    expect(find.text('املاک من'), findsOneWidget);
    expect(find.text('آپارتمان سعادت‌آباد'), findsOneWidget);
    expect(find.text('ملک جدید'), findsOneWidget);
    expect(find.byType(SearchBar), findsOneWidget);
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

      expect(find.text('مشتریان من'), findsOneWidget);
      expect(find.text('نیما مرادی'), findsOneWidget);
      expect(find.text('مشتری جدید'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('property create path exposes required field validation', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: PropertyFormPage())),
    );
    await tester.scrollUntilVisible(
      find.text('ذخیره ملک'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('ذخیره ملک'));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.text('عنوان ملک'),
      -500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();

    expect(find.text('ملک جدید'), findsOneWidget);
    expect(find.text('این فیلد الزامی است.'), findsWidgets);
  });

  testWidgets('customer create path exposes required field validation', (
    tester,
  ) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: CustomerFormPage())),
    );
    await tester.scrollUntilVisible(
      find.text('ذخیره مشتری'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('ذخیره مشتری'));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.text('اطلاعات تماس'),
      -500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();

    expect(find.text('مشتری جدید'), findsOneWidget);
    expect(find.text('الزامی است.'), findsWidgets);
  });
}

final class _FakePropertyRepository extends PropertyRepository {
  _FakePropertyRepository() : super(ApiClient());

  @override
  Future<PagedResult<PropertyRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async => PagedResult<PropertyRecord>(
    items: <PropertyRecord>[
      PropertyRecord.fromJson(<String, dynamic>{
        'id': 1,
        'title': 'آپارتمان سعادت‌آباد',
        'code': 'PR-0001',
        'status': 'available',
        'property_type': 'apartment',
        'transaction_type': 'sale',
        'city': 'تهران',
      }),
    ],
    currentPage: 1,
    lastPage: 1,
  );
}

final class _FakeCustomerRepository extends CustomerRepository {
  _FakeCustomerRepository() : super(ApiClient());

  @override
  Future<PagedResult<CustomerRecord>> list({
    required int page,
    Map<String, Object?> filters = const <String, Object?>{},
  }) async => PagedResult<CustomerRecord>(
    items: <CustomerRecord>[
      CustomerRecord.fromJson(<String, dynamic>{
        'id': 1,
        'first_name': 'نیما',
        'last_name': 'مرادی',
        'mobile': '+989121234567',
        'status': 'active',
        'intent': 'buy',
      }),
    ],
    currentPage: 1,
    lastPage: 1,
  );
}
