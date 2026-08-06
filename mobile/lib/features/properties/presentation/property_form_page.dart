import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/features/owners/presentation/owner_selector.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class PropertyFormPage extends ConsumerStatefulWidget {
  const PropertyFormPage({this.propertyId, super.key});
  final int? propertyId;

  @override
  ConsumerState<PropertyFormPage> createState() => _PropertyFormPageState();
}

final class _PropertyFormPageState extends ConsumerState<PropertyFormPage> {
  final _formKey = GlobalKey<FormState>();
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _salePrice = TextEditingController();
  final _deposit = TextEditingController();
  final _rent = TextEditingController();
  final _area = TextEditingController();
  final _bedrooms = TextEditingController();
  final _bathrooms = TextEditingController();
  final _city = TextEditingController();
  final _district = TextEditingController();
  final _address = TextEditingController();
  String _type = 'apartment';
  String _transaction = 'sale';
  bool _storage = false;
  bool _elevator = false;
  bool _balcony = false;
  bool _loading = false;
  bool _saving = false;
  String? _error;
  PropertyRecord? _original;
  final List<Map<String, dynamic>> _owners = <Map<String, dynamic>>[];

  bool get _editing => widget.propertyId != null;

  @override
  void initState() {
    super.initState();
    if (_editing) Future<void>.microtask(_load);
  }

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _title,
      _description,
      _salePrice,
      _deposit,
      _rent,
      _area,
      _bedrooms,
      _bathrooms,
      _city,
      _district,
      _address,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_editing ? 'ویرایش ملک' : 'ملک جدید')),
    body: _loading
        ? const Center(child: CircularProgressIndicator())
        : Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
              children: <Widget>[
                Text(
                  'اطلاعات اصلی',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                _field(_title, 'عنوان ملک', required: true),
                const SizedBox(height: 12),
                _field(_description, 'توضیحات', lines: 3),
                const SizedBox(height: 12),
                ChoiceField(
                  value: _type,
                  label: 'نوع ملک',
                  items: propertyTypes,
                  onChanged: (v) => setState(() => _type = v!),
                ),
                const SizedBox(height: 12),
                ChoiceField(
                  value: _transaction,
                  label: 'نوع معامله',
                  items: transactionTypes,
                  onChanged: (v) => setState(() => _transaction = v!),
                ),
                const SizedBox(height: 12),
                if (_transaction == 'sale')
                  _numberField(_salePrice, 'قیمت فروش', required: true)
                else ...<Widget>[
                  _numberField(_deposit, 'مبلغ ودیعه', required: true),
                  const SizedBox(height: 12),
                  _numberField(_rent, 'اجاره ماهانه', required: true),
                ],
                const SizedBox(height: 24),
                Text(
                  'نشانی و مشخصات',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 12),
                _field(_city, 'شهر', required: true),
                const SizedBox(height: 12),
                _field(_district, 'محله'),
                const SizedBox(height: 12),
                _field(_address, 'نشانی', required: true, lines: 2),
                const SizedBox(height: 12),
                Row(
                  children: <Widget>[
                    Expanded(child: _numberField(_area, 'متراژ')),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _numberField(
                        _bedrooms,
                        'اتاق خواب',
                        integer: true,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _numberField(_bathrooms, 'سرویس', integer: true),
                    ),
                  ],
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('انباری'),
                  value: _storage,
                  onChanged: (value) => setState(() => _storage = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('آسانسور'),
                  value: _elevator,
                  onChanged: (value) => setState(() => _elevator = value),
                ),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('بالکن'),
                  value: _balcony,
                  onChanged: (value) => setState(() => _balcony = value),
                ),
                const SizedBox(height: 24),
                Text('مالکان', style: Theme.of(context).textTheme.titleLarge),
                const SizedBox(height: 8),
                if (_owners.isEmpty)
                  const Text('برای ثبت ملک حداقل یک مالک انتخاب کنید.'),
                ..._owners.map(
                  (owner) => Card(
                    child: ListTile(
                      leading: const Icon(Icons.badge_outlined),
                      title: Text(_ownerName(owner)),
                      subtitle: Text(
                        '${owner['mobile'] ?? owner['email'] ?? ''}',
                      ),
                      trailing: _editing
                          ? null
                          : IconButton(
                              tooltip: 'حذف مالک',
                              onPressed: () =>
                                  setState(() => _owners.remove(owner)),
                              icon: const Icon(Icons.close_rounded),
                            ),
                    ),
                  ),
                ),
                if (_editing)
                  const Text('تغییر مالکان در نسخه MVP از پنل وب انجام می‌شود.')
                else
                  OwnerSelector(
                    onSelected: (owner) {
                      final id = (owner['id'] as num).toInt();
                      if (_owners.any(
                        (item) => (item['id'] as num).toInt() == id,
                      )) {
                        return;
                      }
                      setState(() => _owners.add(owner));
                    },
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
                  label: Text(_saving ? 'در حال ثبت…' : 'ذخیره ملک'),
                ),
              ],
            ),
          ),
  );

  TextFormField _field(
    TextEditingController controller,
    String label, {
    bool required = false,
    int lines = 1,
  }) => TextFormField(
    controller: controller,
    minLines: lines,
    maxLines: lines,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) => required && (value?.trim().isEmpty ?? true)
        ? 'این فیلد الزامی است.'
        : null,
  );

  TextFormField _numberField(
    TextEditingController controller,
    String label, {
    bool required = false,
    bool integer = false,
  }) => TextFormField(
    controller: controller,
    keyboardType: TextInputType.numberWithOptions(decimal: !integer),
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) {
      final text = value?.trim() ?? '';
      if (required && text.isEmpty) return 'الزامی است.';
      if (text.isNotEmpty && num.tryParse(text) == null) {
        return 'عدد معتبر وارد کنید.';
      }
      return null;
    },
  );

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final item = await ref
          .read(propertyRepositoryProvider)
          .find(widget.propertyId!);
      _original = item;
      _title.text = '${item.data['title'] ?? ''}';
      _description.text = '${item.data['description'] ?? ''}';
      _salePrice.text = '${item.data['sale_price'] ?? ''}';
      _deposit.text = '${item.data['deposit_amount'] ?? ''}';
      _rent.text = '${item.data['monthly_rent'] ?? ''}';
      _area.text = '${item.data['area_sqm'] ?? ''}';
      _bedrooms.text = '${item.data['bedrooms'] ?? ''}';
      _bathrooms.text = '${item.data['bathrooms'] ?? ''}';
      _city.text = '${item.data['city'] ?? ''}';
      _district.text = '${item.data['district'] ?? ''}';
      _address.text = '${item.data['street_address'] ?? ''}';
      _type = item.propertyType;
      _transaction = item.transactionType;
      _storage = item.data['has_storage_room'] == true;
      _elevator = item.data['has_elevator'] == true;
      _balcony = item.data['has_balcony'] == true;
      _owners.addAll(item.owners);
    } catch (error) {
      _error = apiFailureFrom(error).message;
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (!_editing && _owners.isEmpty) {
      setState(() => _error = 'حداقل یک مالک را انتخاب یا ایجاد کنید.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    final body = <String, Object?>{
      'title': _title.text.trim(),
      'description': _empty(_description.text),
      'property_type': _type,
      'transaction_type': _transaction,
      'sale_price': _transaction == 'sale' ? _empty(_salePrice.text) : null,
      'deposit_amount': _transaction == 'rent' ? _empty(_deposit.text) : null,
      'monthly_rent': _transaction == 'rent' ? _empty(_rent.text) : null,
      'area_sqm': _empty(_area.text),
      'bedrooms': int.tryParse(_bedrooms.text),
      'bathrooms': int.tryParse(_bathrooms.text),
      'city': _city.text.trim(),
      'district': _empty(_district.text),
      'street_address': _address.text.trim(),
      'has_storage_room': _storage,
      'has_elevator': _elevator,
      'has_balcony': _balcony,
    };
    try {
      if (_editing) {
        body['expected_updated_at'] = _original!.updatedAt;
        await ref
            .read(propertyRepositoryProvider)
            .update(widget.propertyId!, body);
      } else {
        body['owners'] = _owners
            .asMap()
            .entries
            .map(
              (entry) => <String, Object?>{
                'owner_id': (entry.value['id'] as num).toInt(),
                'ownership_percentage': entry.key == 0 ? '100.00' : null,
                'is_primary': entry.key == 0,
              },
            )
            .toList(growable: false);
        await ref.read(propertyRepositoryProvider).create(body);
      }
      ref.invalidate(propertiesProvider);
      if (_editing) ref.invalidate(propertyProvider(widget.propertyId!));
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
  static String _ownerName(Map<String, dynamic> owner) =>
      owner['owner_type'] == 'company'
      ? '${owner['company_name'] ?? owner['display_name'] ?? 'شرکت'}'
      : '${owner['display_name'] ?? '${owner['first_name'] ?? ''} ${owner['last_name'] ?? ''}'}'
            .trim();
}
