import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final user = auth.user ?? <String, dynamic>{};
    final agency = user['agency'] is Map
        ? Map<String, dynamic>.from(user['agency'] as Map)
        : <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(title: const Text('حساب من')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: <Widget>[
                  const CircleAvatar(
                    radius: 38,
                    child: Icon(Icons.person_rounded, size: 42),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    '${user['name'] ?? ''}',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  Text('${user['email'] ?? ''}'),
                  if (user['phone'] != null) Text('${user['phone']}'),
                  const Divider(height: 28),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.business_outlined),
                    title: Text('${agency['name'] ?? 'آژانس'}'),
                    subtitle: Text('${agency['timezone'] ?? ''}'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: <Widget>[
                ListTile(
                  leading: const Icon(Icons.edit_outlined),
                  title: const Text('ویرایش مشخصات'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => _editProfile(context, ref, user),
                ),
                const Divider(height: 1),
                ListTile(
                  leading: const Icon(Icons.password_outlined),
                  title: const Text('تغییر رمز عبور'),
                  trailing: const Icon(Icons.chevron_left),
                  onTap: () => _changePassword(context, ref),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: auth.isBusy
                ? null
                : () => ref.read(authControllerProvider.notifier).logout(),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('خروج از این دستگاه'),
          ),
          TextButton.icon(
            onPressed: auth.isBusy
                ? null
                : () => ref
                      .read(authControllerProvider.notifier)
                      .logout(allDevices: true),
            icon: const Icon(Icons.phonelink_erase_outlined),
            label: const Text('خروج از همه دستگاه‌ها'),
          ),
          const SizedBox(height: 22),
          const Center(child: BrandSignature(compact: true)),
          const SizedBox(height: 14),
        ],
      ),
    );
  }

  Future<void> _editProfile(
    BuildContext context,
    WidgetRef ref,
    Map<String, dynamic> user,
  ) async {
    final values = await showDialog<_ProfileEditValues>(
      context: context,
      builder: (_) => _ProfileEditDialog(user: user),
    );
    if (values == null ||
        values.name.trim().isEmpty ||
        values.email.trim().isEmpty) {
      return;
    }
    final ok = await ref
        .read(authControllerProvider.notifier)
        .updateProfile(
          name: values.name,
          email: values.email,
          phone: values.phone,
        );
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            ref.read(authControllerProvider).message ?? 'ویرایش انجام نشد.',
          ),
        ),
      );
    }
  }

  Future<void> _changePassword(BuildContext context, WidgetRef ref) async {
    final values = await showDialog<_PasswordChangeValues>(
      context: context,
      builder: (_) => const _PasswordChangeDialog(),
    );
    if (values == null) return;
    if (values.password != values.confirmation ||
        !_isStrongPassword(values.password)) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('رمز جدید معتبر نیست یا تکرار آن یکسان نیست.'),
          ),
        );
      }
      return;
    }
    final ok = await ref
        .read(authControllerProvider.notifier)
        .changePassword(
          currentPassword: values.current,
          password: values.password,
        );
    if (!ok && context.mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            ref.read(authControllerProvider).message ?? 'تغییر رمز انجام نشد.',
          ),
        ),
      );
    }
  }

  static bool _isStrongPassword(String value) =>
      value.length >= 12 &&
      RegExp('[a-z]').hasMatch(value) &&
      RegExp('[A-Z]').hasMatch(value) &&
      RegExp('[0-9]').hasMatch(value) &&
      RegExp(r'[^A-Za-z0-9]').hasMatch(value);
}

typedef _ProfileEditValues = ({String name, String email, String phone});
typedef _PasswordChangeValues = ({
  String current,
  String password,
  String confirmation,
});

final class _ProfileEditDialog extends StatefulWidget {
  const _ProfileEditDialog({required this.user});

  final Map<String, dynamic> user;

  @override
  State<_ProfileEditDialog> createState() => _ProfileEditDialogState();
}

final class _ProfileEditDialogState extends State<_ProfileEditDialog> {
  late final TextEditingController _name;
  late final TextEditingController _email;
  late final TextEditingController _phone;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: '${widget.user['name'] ?? ''}');
    _email = TextEditingController(text: '${widget.user['email'] ?? ''}');
    _phone = TextEditingController(text: '${widget.user['phone'] ?? ''}');
  }

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('ویرایش مشخصات'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        TextField(
          controller: _name,
          decoration: const InputDecoration(labelText: 'نام'),
        ),
        TextField(
          controller: _email,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(labelText: 'ایمیل'),
        ),
        TextField(
          controller: _phone,
          keyboardType: TextInputType.phone,
          decoration: const InputDecoration(labelText: 'تلفن'),
        ),
      ],
    ),
    actions: <Widget>[
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('انصراف'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, (
          name: _name.text,
          email: _email.text,
          phone: _phone.text,
        )),
        child: const Text('ثبت'),
      ),
    ],
  );
}

final class _PasswordChangeDialog extends StatefulWidget {
  const _PasswordChangeDialog();

  @override
  State<_PasswordChangeDialog> createState() => _PasswordChangeDialogState();
}

final class _PasswordChangeDialogState extends State<_PasswordChangeDialog> {
  late final TextEditingController _current;
  late final TextEditingController _password;
  late final TextEditingController _confirmation;

  @override
  void initState() {
    super.initState();
    _current = TextEditingController();
    _password = TextEditingController();
    _confirmation = TextEditingController();
  }

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('تغییر رمز عبور'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        TextField(
          controller: _current,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'رمز فعلی'),
        ),
        TextField(
          controller: _password,
          obscureText: true,
          decoration: const InputDecoration(
            labelText: 'رمز جدید (حداقل ۱۲ نویسه)',
          ),
        ),
        TextField(
          controller: _confirmation,
          obscureText: true,
          decoration: const InputDecoration(labelText: 'تکرار رمز جدید'),
        ),
      ],
    ),
    actions: <Widget>[
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('انصراف'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, (
          current: _current.text,
          password: _password.text,
          confirmation: _confirmation.text,
        )),
        child: const Text('ثبت'),
      ),
    ],
  );
}
