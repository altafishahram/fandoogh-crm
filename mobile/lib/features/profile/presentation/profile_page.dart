import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:fandoogh_crm/core/offline/sync_controller.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final queue = ref.watch(syncControllerProvider);
    final user = auth.user ?? <String, dynamic>{};
    final agency = user['agency'] is Map
        ? Map<String, dynamic>.from(user['agency'] as Map)
        : <String, dynamic>{};
    return Scaffold(
      appBar: AppBar(
        title: const Text('حساب من'),
        actions: [
          if (auth.isManager)
            IconButton(
              tooltip: 'موقعیت پیش‌فرض آژانس',
              onPressed: () => context.push('/agency-location'),
              icon: const Icon(Icons.location_on_outlined),
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: <Widget>[
          Container(
            padding: const EdgeInsets.all(22),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
                colors: <Color>[
                  Color(0xFF115E59),
                  Color(0xFF0F766E),
                  Color(0xFF17988C),
                ],
              ),
              borderRadius: BorderRadius.circular(26),
              boxShadow: const <BoxShadow>[
                BoxShadow(
                  color: Color(0x2E0F5E59),
                  blurRadius: 28,
                  offset: Offset(0, 12),
                ),
              ],
            ),
            child: Column(
              children: <Widget>[
                Container(
                  width: 78,
                  height: 78,
                  alignment: Alignment.center,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE4FFF8),
                    borderRadius: BorderRadius.circular(25),
                    border: Border.all(color: Colors.white70, width: 3),
                  ),
                  child: Text(
                    _profileInitial('${user['name'] ?? ''}'),
                    style: const TextStyle(
                      color: Color(0xFF115E59),
                      fontSize: 27,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  '${user['name'] ?? ''}',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  '${user['email'] ?? ''}',
                  style: const TextStyle(color: Color(0xFFD6F6EF)),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 11,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: .13),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    auth.isManager ? 'مدیر آژانس' : 'کارشناس آژانس',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
                if (user['phone'] != null) ...<Widget>[
                  const SizedBox(height: 5),
                  Text(
                    '${user['phone']}',
                    style: const TextStyle(color: Colors.white),
                  ),
                ],
                const SizedBox(height: 14),
                const Divider(color: Colors.white24),
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(
                    Icons.business_outlined,
                    color: Colors.white,
                  ),
                  title: Text(
                    '${agency['name'] ?? 'آژانس'}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  subtitle: Text(
                    '${agency['timezone'] ?? ''}',
                    style: const TextStyle(color: Color(0xFFD6F6EF)),
                  ),
                ),
              ],
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
          if ((queue.value?.isNotEmpty ?? false))
            Card(
              child: ListTile(
                leading: const Icon(Icons.cloud_upload_outlined),
                title: Text(
                  '${queue.value!.length} عملیات در انتظار همگام‌سازی',
                ),
                subtitle: const Text(
                  'پیش از خروج، اتصال اینترنت و همگام‌سازی را بررسی کنید.',
                ),
                trailing: IconButton(
                  tooltip: 'همگام‌سازی',
                  onPressed: queue.isLoading
                      ? null
                      : ref.read(syncControllerProvider.notifier).synchronize,
                  icon: const Icon(Icons.sync_rounded),
                ),
              ),
            ),
          if ((queue.value?.isNotEmpty ?? false)) const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: auth.isBusy ? null : () => _confirmLogout(context, ref),
            icon: const Icon(Icons.logout_rounded),
            label: const Text('خروج از این دستگاه'),
          ),
          TextButton.icon(
            onPressed: auth.isBusy
                ? null
                : () => _confirmLogout(context, ref, allDevices: true),
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

  Future<void> _confirmLogout(
    BuildContext context,
    WidgetRef ref, {
    bool allDevices = false,
  }) async {
    final pending = await ref.read(offlineStoreProvider).pendingCount();
    if (!context.mounted) return;
    if (pending > 0) {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('عملیات همگام‌نشده'),
          content: Text(
            '$pending عملیات هنوز به سرور ارسال نشده است. با خروج، این اطلاعات از دستگاه حذف می‌شود. آیا مطمئن هستید؟',
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('انصراف'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('خروج و حذف اطلاعات'),
            ),
          ],
        ),
      );
      if (confirmed != true) return;
    }
    await ref
        .read(authControllerProvider.notifier)
        .logout(allDevices: allDevices);
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

  static String _profileInitial(String name) {
    final value = name.trim();
    return value.isEmpty ? '؟' : value.characters.first;
  }
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
