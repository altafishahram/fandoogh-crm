import 'package:fandoogh_crm/features/geography/geography.dart';
import 'dart:async';

import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class OwnerSelector extends ConsumerStatefulWidget {
  const OwnerSelector({required this.onSelected, super.key});
  final ValueChanged<Map<String, dynamic>> onSelected;

  @override
  ConsumerState<OwnerSelector> createState() => _OwnerSelectorState();
}

final class _OwnerSelectorState extends ConsumerState<OwnerSelector> {
  final _query = TextEditingController();
  Timer? _timer;
  AsyncValue<List<Map<String, dynamic>>>? _results;

  @override
  void dispose() {
    _timer?.cancel();
    _query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      TextField(
        controller: _query,
        decoration: InputDecoration(
          labelText: 'جست‌وجوی مالک',
          hintText: 'نام، موبایل یا ایمیل (حداقل ۲ نویسه)',
          border: const OutlineInputBorder(),
          suffixIcon: IconButton(
            tooltip: 'مالک جدید',
            onPressed: _create,
            icon: const Icon(Icons.person_add_alt),
          ),
        ),
        onChanged: (value) {
          _timer?.cancel();
          _timer = Timer(const Duration(milliseconds: 400), _search);
        },
      ),
      if (_results?.isLoading == true) const LinearProgressIndicator(),
      if (_results?.hasError == true)
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: Text(
            'جست‌وجوی مالک ناموفق بود.',
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ),
      if (_results?.hasValue == true)
        ..._results!.requireValue.map(
          (owner) => ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.badge_outlined),
            title: Text(_name(owner)),
            subtitle: Text('${owner['mobile'] ?? owner['email'] ?? ''}'),
            trailing: const Icon(Icons.add_circle_outline),
            onTap: () => widget.onSelected(owner),
          ),
        ),
      const SizedBox(height: 8),
      OutlinedButton.icon(
        onPressed: _create,
        icon: const Icon(Icons.person_add_alt),
        label: const Text('ساخت مالک جدید'),
      ),
    ],
  );

  Future<void> _search() async {
    final query = _query.text.trim();
    if (query.length < 2) {
      setState(() => _results = null);
      return;
    }
    setState(() => _results = const AsyncLoading<List<Map<String, dynamic>>>());
    final value = await AsyncValue.guard(
      () => ApiRepository(
        ref.read(apiClientProvider),
      ).getList('/owners', query: <String, Object?>{'q': query}),
    );
    if (mounted) setState(() => _results = value);
  }

  Future<void> _create() async {
    final owner = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _OwnerFormDialog(),
    );
    if (owner != null) widget.onSelected(owner);
  }

  static String _name(Map<String, dynamic> owner) =>
      owner['owner_type'] == 'company'
      ? owner['company_name'] as String? ?? 'شرکت'
      : '${owner['first_name'] ?? ''} ${owner['last_name'] ?? ''}'.trim();
}

final class _OwnerFormDialog extends ConsumerStatefulWidget {
  const _OwnerFormDialog();

  @override
  ConsumerState<_OwnerFormDialog> createState() => _OwnerFormDialogState();
}

final class _OwnerFormDialogState extends ConsumerState<_OwnerFormDialog> {
  final _formKey = GlobalKey<FormState>();
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _company = TextEditingController();
  final _mobile = TextEditingController();
  final _email = TextEditingController();
  late RegionSelection _region;
  @override
  void initState() {
    super.initState();
    _region = agencyRegion(ref);
  }

  String _type = 'person';
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _first,
      _last,
      _company,
      _mobile,
      _email,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('مالک جدید'),
    content: SingleChildScrollView(
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            SegmentedButton<String>(
              segments: const <ButtonSegment<String>>[
                ButtonSegment(value: 'person', label: Text('شخص')),
                ButtonSegment(value: 'company', label: Text('شرکت')),
              ],
              selected: <String>{_type},
              onSelectionChanged: (value) =>
                  setState(() => _type = value.first),
            ),
            const SizedBox(height: 16),
            if (_type == 'person') ...<Widget>[
              _field(_first, 'نام', required: true),
              const SizedBox(height: 12),
              _field(_last, 'نام خانوادگی', required: true),
            ] else
              _field(_company, 'نام شرکت', required: true),
            const SizedBox(height: 12),
            _field(_mobile, 'موبایل'),
            const SizedBox(height: 12),
            _field(_email, 'ایمیل'),
            const SizedBox(height: 12),
            RegionFields(
              value: _region,
              onChanged: (value) => setState(() => _region = value),
            ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
          ],
        ),
      ),
    ),
    actions: <Widget>[
      TextButton(
        onPressed: _busy ? null : () => Navigator.pop(context),
        child: const Text('انصراف'),
      ),
      FilledButton(
        onPressed: _busy ? null : _submit,
        child: Text(_busy ? 'در حال ثبت…' : 'ثبت'),
      ),
    ],
  );

  TextFormField _field(
    TextEditingController controller,
    String label, {
    bool required = false,
  }) => TextFormField(
    controller: controller,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
    ),
    validator: (value) => required && (value?.trim().isEmpty ?? true)
        ? 'این فیلد الزامی است.'
        : null,
  );

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    if (_mobile.text.trim().isEmpty && _email.text.trim().isEmpty) {
      setState(() => _error = 'حداقل موبایل یا ایمیل را وارد کنید.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await ApiRepository(ref.read(apiClientProvider))
          .post('/owners', <String, Object?>{
            'owner_type': _type,
            ..._region.toJson(),
            if (_type == 'person') 'first_name': _first.text.trim(),
            if (_type == 'person') 'last_name': _last.text.trim(),
            if (_type == 'company') 'company_name': _company.text.trim(),
            'mobile': _mobile.text.trim().isEmpty ? null : _mobile.text.trim(),
            'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
          });
      if (mounted) Navigator.pop(context, result);
    } catch (error) {
      if (mounted) {
        setState(() {
          _busy = false;
          _error = apiFailureFrom(error).message;
        });
      }
    }
  }
}
