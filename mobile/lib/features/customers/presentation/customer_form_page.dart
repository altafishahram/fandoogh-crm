import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CustomerFormPage extends ConsumerStatefulWidget {
  const CustomerFormPage({this.customerId, super.key});
  final int? customerId;
  @override
  ConsumerState<CustomerFormPage> createState() => _CustomerFormPageState();
}

final class _CustomerFormPageState extends ConsumerState<CustomerFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _mobile = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _budgetMin = TextEditingController();
  final _budgetMax = TextEditingController();
  final _city = TextEditingController();
  final _district = TextEditingController();
  final _areaMin = TextEditingController();
  final _areaMax = TextEditingController();
  final _bedrooms = TextEditingController();
  String _contact = 'phone';
  String _intent = 'buy';
  String _status = 'active';
  String? _propertyType;
  CustomerRecord? _original;
  bool _loading = false;
  bool _saving = false;
  String? _error;
  bool get _editing => widget.customerId != null;

  @override
  void initState() {
    super.initState();
    if (_editing) Future<void>.microtask(_load);
  }

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _first,
      _last,
      _mobile,
      _phone,
      _email,
      _budgetMin,
      _budgetMax,
      _city,
      _district,
      _areaMin,
      _areaMax,
      _bedrooms,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_editing ? 'ویرایش مشتری' : 'مشتری جدید')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
              children: <Widget>[
                Text(
                  'اطلاعات تماس',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                Row(
                  children: <Widget>[
                    Expanded(child: _field(_first, 'نام', required: true)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _field(_last, 'نام خانوادگی', required: true),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _field(
                  _mobile,
                  'موبایل',
                  required: true,
                  keyboard: TextInputType.phone,
                ),
                const SizedBox(height: 12),
                _field(_phone, 'تلفن', keyboard: TextInputType.phone),
                const SizedBox(height: 12),
                _field(_email, 'ایمیل', keyboard: TextInputType.emailAddress),
                const SizedBox(height: 12),
                ChoiceField(
                  value: _contact,
                  label: 'روش تماس ترجیحی',
                  items: const <ChoiceItem>[
                    ChoiceItem('phone', 'تلفن'),
                    ChoiceItem('email', 'ایمیل'),
                  ],
                  onChanged: (v) => setState(() => _contact = v!),
                ),
                const SizedBox(height: 24),
                Text(
                  'نیاز مشتری',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                ChoiceField(
                  value: _intent,
                  label: 'قصد معامله',
                  items: customerIntents,
                  onChanged: (v) => setState(() => _intent = v!),
                ),
                if (_editing) ...<Widget>[
                  const SizedBox(height: 12),
                  ChoiceField(
                    value: _status,
                    label: 'وضعیت',
                    items: customerStatuses,
                    onChanged: (v) => setState(() => _status = v!),
                  ),
                ],
                const SizedBox(height: 12),
                ChoiceField(
                  value: _propertyType,
                  label: 'نوع ملک ترجیحی',
                  items: propertyTypes,
                  onChanged: (v) => setState(() => _propertyType = v),
                ),
                const SizedBox(height: 12),
                Row(
                  children: <Widget>[
                    Expanded(child: _number(_budgetMin, 'بودجه از')),
                    const SizedBox(width: 12),
                    Expanded(child: _number(_budgetMax, 'بودجه تا')),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: <Widget>[
                    Expanded(child: _field(_city, 'شهر مطلوب')),
                    const SizedBox(width: 12),
                    Expanded(child: _field(_district, 'محله مطلوب')),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: <Widget>[
                    Expanded(child: _number(_areaMin, 'متراژ از')),
                    const SizedBox(width: 12),
                    Expanded(child: _number(_areaMax, 'متراژ تا')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _number(_bedrooms, 'حداقل خواب', integer: true),
                    ),
                  ],
                ),
                if (_error != null) ...<Widget>[
                  const SizedBox(height: 16),
                  Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: _saving ? null : _submit,
                  icon: _saving
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.save_outlined),
                  label: Text(_saving ? 'در حال ثبت…' : 'ذخیره مشتری'),
                ),
              ],
            ),
          ),
  );

  TextFormField _field(
    TextEditingController controller,
    String label, {
    bool required = false,
    TextInputType? keyboard,
  }) => TextFormField(
    controller: controller,
    keyboardType: keyboard,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) =>
        required && (value?.trim().isEmpty ?? true) ? 'الزامی است.' : null,
  );
  TextFormField _number(
    TextEditingController controller,
    String label, {
    bool integer = false,
  }) => TextFormField(
    controller: controller,
    keyboardType: TextInputType.numberWithOptions(decimal: !integer),
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) =>
        (value?.trim().isNotEmpty == true &&
            num.tryParse(value!.trim()) == null)
        ? 'عدد نامعتبر'
        : null,
  );

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final item = await ref
          .read(customerRepositoryProvider)
          .find(widget.customerId!);
      _original = item;
      _first.text = '${item.data['first_name'] ?? ''}';
      _last.text = '${item.data['last_name'] ?? ''}';
      _mobile.text = '${item.data['mobile'] ?? ''}';
      _phone.text = '${item.data['phone'] ?? ''}';
      _email.text = '${item.data['email'] ?? ''}';
      _budgetMin.text = '${item.data['budget_min'] ?? ''}';
      _budgetMax.text = '${item.data['budget_max'] ?? ''}';
      _city.text = '${item.data['desired_city'] ?? ''}';
      _district.text = '${item.data['desired_district'] ?? ''}';
      _areaMin.text = '${item.data['min_area_sqm'] ?? ''}';
      _areaMax.text = '${item.data['max_area_sqm'] ?? ''}';
      _bedrooms.text = '${item.data['min_bedrooms'] ?? ''}';
      _contact = item.data['preferred_contact_method'] as String? ?? 'phone';
      _intent = item.intent;
      _status = item.status;
      final preferred = item.data['preferred_property_types'];
      if (preferred is List && preferred.isNotEmpty) {
        _propertyType = '${preferred.first}';
      }
    } catch (error) {
      _error = apiFailureFrom(error).message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_contact == 'email' && _email.text.trim().isEmpty) {
      setState(() => _error = 'برای روش تماس ایمیل، آدرس ایمیل را وارد کنید.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final body = <String, Object?>{
      'assigned_agent_id':
          (ref.read(authControllerProvider).user?['id'] as num?)?.toInt(),
      'first_name': _first.text.trim(),
      'last_name': _last.text.trim(),
      'mobile': _mobile.text.trim(),
      'phone': _empty(_phone.text),
      'email': _empty(_email.text),
      'preferred_contact_method': _contact,
      'intent': _intent,
      'preferred_property_types': _propertyType == null
          ? null
          : <String>[_propertyType!],
      'budget_min': _empty(_budgetMin.text),
      'budget_max': _empty(_budgetMax.text),
      'desired_city': _empty(_city.text),
      'desired_district': _empty(_district.text),
      'min_area_sqm': _empty(_areaMin.text),
      'max_area_sqm': _empty(_areaMax.text),
      'min_bedrooms': int.tryParse(_bedrooms.text),
    };
    try {
      if (_editing) {
        body['status'] = _status;
        body['expected_updated_at'] = _original!.updatedAt;
        await ref
            .read(customerRepositoryProvider)
            .update(widget.customerId!, body);
        ref.invalidate(customerProvider(widget.customerId!));
      } else {
        await ref.read(customerRepositoryProvider).create(body);
      }
      ref.invalidate(customersProvider);
      if (mounted) context.pop(true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = apiFailureFrom(error).message;
        });
      }
    }
  }

  static String? _empty(String value) =>
      value.trim().isEmpty ? null : value.trim();
}
