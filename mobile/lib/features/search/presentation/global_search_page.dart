import 'dart:async';

import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class GlobalSearchPage extends ConsumerStatefulWidget {
  const GlobalSearchPage({super.key});

  @override
  ConsumerState<GlobalSearchPage> createState() => _GlobalSearchPageState();
}

final class _GlobalSearchPageState extends ConsumerState<GlobalSearchPage> {
  final _query = TextEditingController();
  Timer? _debounce;
  AsyncValue<Map<String, dynamic>>? _result;

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('جست‌وجوی سراسری')),
    body: Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.all(16),
          child: SearchBar(
            controller: _query,
            autoFocus: true,
            hintText: 'ملک، نام مالک یا مشتری…',
            leading: const Icon(Icons.search_rounded),
            onChanged: _schedule,
            onSubmitted: (_) => _search(),
          ),
        ),
        Expanded(child: _body()),
      ],
    ),
  );

  Widget _body() {
    if (_query.text.trim().length < 2) {
      return const EmptyState(
        message: 'حداقل دو نویسه برای جست‌وجو وارد کنید.',
        icon: Icons.manage_search,
      );
    }
    final result = _result;
    if (result == null || result.isLoading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (result.hasError) {
      return ErrorState(error: result.error!, onRetry: _search);
    }
    final data = result.requireValue;
    final properties = _maps(data['properties']);
    final customers = _maps(data['customers']);
    if (properties.isEmpty && customers.isEmpty) {
      return const EmptyState(message: 'نتیجه‌ای پیدا نشد.');
    }
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      children: <Widget>[
        if (properties.isNotEmpty) ...<Widget>[
          const _Header('املاک'),
          ...properties.map(
            (item) => ListTile(
              leading: const Icon(Icons.apartment_outlined),
              title: Text('${item['title']}'),
              subtitle: Text('${item['code']} • ${item['city']}'),
              onTap: () => context.push('/properties/${item['id']}'),
            ),
          ),
        ],
        if (customers.isNotEmpty) ...<Widget>[
          const _Header('مشتریان'),
          ...customers.map(
            (item) => ListTile(
              leading: const Icon(Icons.person_outline),
              title: Text(
                '${item['full_name'] ?? '${item['first_name'] ?? ''} ${item['last_name'] ?? ''}'}',
              ),
              subtitle: Text('${item['mobile'] ?? item['email'] ?? ''}'),
              onTap: () => context.push('/customers/${item['id']}'),
            ),
          ),
        ],
      ],
    );
  }

  void _schedule(String _) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 350), _search);
    setState(() {});
  }

  Future<void> _search() async {
    final query = _query.text.trim();
    if (query.length < 2) {
      setState(() => _result = null);
      return;
    }
    setState(() => _result = const AsyncLoading<Map<String, dynamic>>());
    final value = await AsyncValue.guard(
      () => ApiRepository(
        ref.read(apiClientProvider),
      ).getOne('/search?q=${Uri.encodeQueryComponent(query)}'),
    );
    if (mounted) setState(() => _result = value);
  }

  static List<Map<String, dynamic>> _maps(Object? value) => value is List
      ? value
            .whereType<Map>()
            .map(Map<String, dynamic>.from)
            .toList(growable: false)
      : <Map<String, dynamic>>[];
}

final class _Header extends StatelessWidget {
  const _Header(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 16, bottom: 4),
    child: Text(text, style: Theme.of(context).textTheme.titleMedium),
  );
}
