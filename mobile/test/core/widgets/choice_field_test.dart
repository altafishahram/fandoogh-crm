import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('floats the label when the empty option is displayed', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ChoiceField(
            value: null,
            label: 'نوع کابینت',
            items: const <ChoiceItem>[ChoiceItem('mdf', 'ام‌دی‌اف')],
            includeEmpty: true,
            onChanged: (_) {},
          ),
        ),
      ),
    );

    final decorator = tester.widget<InputDecorator>(
      find.byType(InputDecorator),
    );
    expect(decorator.isEmpty, isFalse);
    expect(find.text('نوع کابینت'), findsOneWidget);
    expect(find.text('انتخاب نشده'), findsOneWidget);
  });

  testWidgets('keeps a long selected value on one readable line', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ChoiceField(
            value: 'land',
            label: 'نوع ملک',
            items: const <ChoiceItem>[ChoiceItem('land', 'زمین و ملک کلنگی')],
            onChanged: (_) {},
          ),
        ),
      ),
    );

    final valueText = tester.widget<Text>(find.text('زمین و ملک کلنگی'));
    expect(valueText.maxLines, 1);
    expect(valueText.overflow, TextOverflow.ellipsis);
    expect(valueText.softWrap, isFalse);
  });
}
