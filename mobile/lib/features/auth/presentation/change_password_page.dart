import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ChangePasswordPage extends ConsumerStatefulWidget {
  const ChangePasswordPage({super.key});

  @override
  ConsumerState<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

final class _ChangePasswordPageState extends ConsumerState<ChangePasswordPage> {
  final _formKey = GlobalKey<FormState>();
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('تغییر رمز عبور الزامی')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: GlassPanel(
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: <Widget>[
                      const BrandSignature(),
                      const SizedBox(height: 16),
                      const Text(
                        'برای ادامه، رمز موقت را با یک رمز امن جایگزین کنید. رمز جدید باید حداقل ۱۲ نویسه و شامل حرف کوچک، بزرگ، عدد و نماد باشد.',
                        textAlign: TextAlign.center,
                      ),
                      if (auth.message != null) ...<Widget>[
                        const SizedBox(height: 16),
                        Text(
                          auth.message!,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ],
                      const SizedBox(height: 24),
                      _passwordField(_current, 'رمز فعلی'),
                      const SizedBox(height: 16),
                      _passwordField(
                        _password,
                        'رمز جدید',
                        validateStrong: true,
                      ),
                      const SizedBox(height: 16),
                      _passwordField(
                        _confirmation,
                        'تکرار رمز جدید',
                        confirmation: true,
                      ),
                      const SizedBox(height: 24),
                      FilledButton.icon(
                        onPressed: auth.isBusy ? null : _submit,
                        icon: auth.isBusy
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Icon(Icons.check_rounded),
                        label: const Text('ثبت رمز و ادامه'),
                      ),
                      TextButton(
                        onPressed: auth.isBusy
                            ? null
                            : () => ref
                                  .read(authControllerProvider.notifier)
                                  .logout(),
                        child: const Text('خروج از حساب'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _passwordField(
    TextEditingController controller,
    String label, {
    bool validateStrong = false,
    bool confirmation = false,
  }) => TextFormField(
    controller: controller,
    obscureText: _obscure,
    decoration: InputDecoration(
      labelText: label,
      border: const OutlineInputBorder(),
      suffixIcon: IconButton(
        tooltip: _obscure ? 'نمایش رمزها' : 'پنهان‌کردن رمزها',
        onPressed: () => setState(() => _obscure = !_obscure),
        icon: Icon(
          _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
        ),
      ),
    ),
    validator: (value) {
      final text = value ?? '';
      if (text.isEmpty) return 'این فیلد الزامی است.';
      if (validateStrong &&
          (text.length < 12 ||
              !RegExp('[a-z]').hasMatch(text) ||
              !RegExp('[A-Z]').hasMatch(text) ||
              !RegExp('[0-9]').hasMatch(text) ||
              !RegExp(r'[^A-Za-z0-9]').hasMatch(text))) {
        return 'رمز انتخابی معیارهای امنیتی را ندارد.';
      }
      if (confirmation && text != _password.text) {
        return 'تکرار رمز یکسان نیست.';
      }
      return null;
    },
  );

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    await ref
        .read(authControllerProvider.notifier)
        .changePassword(
          currentPassword: _current.text,
          password: _password.text,
        );
  }
}
