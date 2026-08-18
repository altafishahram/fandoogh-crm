import 'package:fandoogh_crm/core/localization/persian_number.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('normalizes Persian digits and presentation separators', () {
    expect(normalizeNumericText('۱٬۲۳۴٬۵۶۷'), '1234567');
    expect(normalizeNumericText('۱۲۳۴۵۶۷٫۵'), '1234567.5');
    expect(normalizeNumericText('1,234,567.5'), '1234567.5');
  });

  test('formats numeric input with Persian digits and thousands grouping', () {
    expect(formatPersianNumberInput('1234567'), '۱٬۲۳۴٬۵۶۷');
    expect(formatPersianNumberInput('۱۲۳۴۵۶۷٫۵'), '۱٬۲۳۴٬۵۶۷٫۵');
    expect(formatPersianNumberInput('1234567.5', integer: true), '۱٬۲۳۴٬۵۶۷');
  });

  test('converts monetary values to Persian words', () {
    expect(amountInWords('۱٬۰۰۰٬۰۰۰'), 'یک میلیون');
    expect(
      amountInWords('۱۲۳۴۵۶۷'),
      'یک میلیون و دویست و سی و چهار هزار و پانصد و شصت و هفت',
    );
    expect(formatPersianDigits('09121234567'), '۰۹۱۲۱۲۳۴۵۶۷');
  });

  testWidgets('amount hint follows the edited controller value', (
    tester,
  ) async {
    final controller = TextEditingController(text: '1000000');
    addTearDown(controller.dispose);

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: AmountInWordsHint(controller: controller)),
      ),
    );
    expect(find.text('به حروف: یک میلیون تومان'), findsOneWidget);

    controller.text = '2500000';
    await tester.pump();
    expect(find.text('به حروف: دو میلیون و پانصد هزار تومان'), findsOneWidget);
  });
}
