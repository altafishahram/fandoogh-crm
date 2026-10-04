import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class AgencyLocationPage extends ConsumerStatefulWidget {
  const AgencyLocationPage({super.key});
  @override
  ConsumerState<AgencyLocationPage> createState() => _AgencyLocationPageState();
}

final class _AgencyLocationPageState extends ConsumerState<AgencyLocationPage> {
  late RegionSelection _region;
  final _form = GlobalKey<FormState>();
  bool _saving = false;
  String? _error;
  @override
  void initState() {
    super.initState();
    _region = agencyRegion(ref);
  }

  Future<void> _save() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await ref
          .read(apiClientProvider)
          .dio
          .put<Object?>('/agency/location', data: _region.toJson());
      await ref.read(authControllerProvider.notifier).restoreSession();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('موقعیت پیش‌فرض آژانس ذخیره شد.')),
        );
      }
    } catch (e) {
      if (mounted) setState(() => _error = apiFailureFrom(e).message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('موقعیت پیش‌فرض آژانس')),
    body: Form(
      key: _form,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'فرم‌ها و فیلترهای جدید با این منطقه آغاز می‌شوند؛ موقعیت ملک‌ها و مشتری‌های قبلی تغییر نمی‌کند.',
          ),
          const SizedBox(height: 16),
          RegionFields(
            value: _region,
            requiredCity: true,
            onChanged: (value) => setState(() => _region = value),
          ),
          if (_error != null)
            Text(
              _error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          FilledButton(
            onPressed: !_saving && ref.watch(authControllerProvider).isManager
                ? _save
                : null,
            child: Text(_saving ? 'در حال ذخیره…' : 'ذخیره'),
          ),
        ],
      ),
    ),
  );
}
