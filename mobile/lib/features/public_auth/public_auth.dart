import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final publicSessionProvider =
    NotifierProvider<PublicSession, AsyncValue<Map<String, dynamic>?>>(
      PublicSession.new,
    );
final publicAuthConfigProvider = FutureProvider<Map<String, dynamic>>(
  (ref) =>
      ApiRepository(ref.watch(apiClientProvider)).getOne('/public/auth/config'),
);

final class PublicSession extends Notifier<AsyncValue<Map<String, dynamic>?>> {
  int _revision = 0;
  @override
  AsyncValue<Map<String, dynamic>?> build() {
    final client = ref.read(apiClientProvider);
    client.onAccessFailure = (failure) {
      if (failure.statusCode == 401) logout(localOnly: true);
    };
    ref.onDispose(() => client.onAccessFailure = null);
    Future<void>.microtask(_restore);
    return const AsyncLoading();
  }

  Future<void> _restore() async {
    final revision = ++_revision;
    final tokens = ref.read(tokenStoreProvider);
    final client = ref.read(apiClientProvider);
    try {
      client.token = await tokens.read();
      if (client.token == null) {
        state = const AsyncData(null);
        return;
      }
      final user = await ApiRepository(client).getOne('/public/auth/me');
      if (revision == _revision) state = AsyncData(user);
    } catch (e) {
      // Public listings never restore an offline/CRM session from a cached identity.
      if (revision == _revision) {
        client.token = null;
        await tokens.clear();
        state = const AsyncData(null);
      }
    }
  }

  Future<void> login(String phone, String code) async {
    final revision = ++_revision;
    final client = ref.read(apiClientProvider);
    final payload = await ApiRepository(client).post('/public/auth/login', {
      'phone': phone,
      'code': code,
      'device_name': 'دفتر املاکی عمومی اندروید',
    });
    final token = payload['token'] as String;
    if (revision != _revision) return;
    await ref.read(tokenStoreProvider).write(token);
    client.token = token;
    state = AsyncData(Map<String, dynamic>.from(payload['user'] as Map));
  }

  Future<void> logout({bool localOnly = false}) async {
    ++_revision;
    final client = ref.read(apiClientProvider);
    try {
      if (!localOnly) await client.dio.post<void>('/public/auth/logout');
    } catch (_) {
      /* Local logout remains available offline. */
    } finally {
      client.token = null;
      await ref.read(tokenStoreProvider).clear();
      state = const AsyncData(null);
    }
  }
}

final class PublicLoginPage extends ConsumerStatefulWidget {
  const PublicLoginPage({super.key});
  @override
  ConsumerState<PublicLoginPage> createState() => _PublicLoginPageState();
}

final class _PublicLoginPageState extends ConsumerState<PublicLoginPage> {
  final _phone = TextEditingController(), _code = TextEditingController();
  final _form = GlobalKey<FormState>();
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _phone.dispose();
    _code.dispose();
    super.dispose();
  }

  String _digits(String input) {
    const fa = '۰۱۲۳۴۵۶۷۸۹', ar = '٠١٢٣٤٥٦٧٨٩';
    return input
        .split('')
        .map(
          (c) => fa.contains(c)
              ? '${fa.indexOf(c)}'
              : ar.contains(c)
              ? '${ar.indexOf(c)}'
              : c,
        )
        .join()
        .replaceAll(' ', '');
  }

  Future<void> _login() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await ref
          .read(publicSessionProvider.notifier)
          .login(_digits(_phone.text), _digits(_code.text));
    } catch (e) {
      if (mounted) setState(() => _error = apiFailureFrom(e).message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(publicAuthConfigProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('دفتر املاکی عمومی')),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 480),
            child: Form(
              key: _form,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.home_work_outlined, size: 64),
                  const SizedBox(height: 20),
                  Text(
                    'ورود برای مشاهده آگهی‌ها',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 24),
                  TextFormField(
                    controller: _phone,
                    keyboardType: TextInputType.phone,
                    autofillHints: const [AutofillHints.telephoneNumber],
                    decoration: const InputDecoration(
                      labelText: 'شماره موبایل',
                    ),
                    validator: (v) =>
                        RegExp(r'^09\d{9}$').hasMatch(_digits(v ?? ''))
                        ? null
                        : 'شماره موبایل معتبر وارد کنید.',
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _code,
                    keyboardType: TextInputType.number,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'رمز ورود موقت',
                    ),
                    validator: (v) => (v?.trim().isEmpty ?? true)
                        ? 'رمز موقت را وارد کنید.'
                        : null,
                  ),
                  config.when(
                    data: (c) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        'کد موقت آزمایشی: ${persianDigits('${c['temporary_code'] ?? ''}')}\nشماره موبایل در این مرحله تأیید نمی‌شود.',
                      ),
                    ),
                    loading: () => const LinearProgressIndicator(),
                    error: (e, _) => TextButton(
                      onPressed: () => ref.invalidate(publicAuthConfigProvider),
                      child: const Text('دریافت رمز موقت — تلاش دوباره'),
                    ),
                  ),
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  FilledButton(
                    onPressed: _busy || !config.hasValue ? null : _login,
                    child: _busy
                        ? const SizedBox(
                            height: 24,
                            width: 24,
                            child: CircularProgressIndicator(),
                          )
                        : const Text('ورود'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

final class PublicAccountPage extends ConsumerWidget {
  const PublicAccountPage({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
    appBar: AppBar(title: const Text('حساب عمومی')),
    body: ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text(
          persianDigits(
            '${ref.watch(publicSessionProvider).value?['phone'] ?? ''}',
          ),
        ),
        const Text('حساب عمومی · شماره تأییدنشده'),
        const SizedBox(height: 20),
        FilledButton.icon(
          onPressed: () => ref.read(publicSessionProvider.notifier).logout(),
          icon: const Icon(Icons.logout),
          label: const Text('خروج'),
        ),
      ],
    ),
  );
}
