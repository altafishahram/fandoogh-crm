import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/widgets/section_card.dart';
import 'package:fandoogh_crm/core/widgets/text_input_dialog.dart';
import 'package:fandoogh_crm/features/customers/data/customer_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CustomerDetailPage extends ConsumerWidget {
  const CustomerDetailPage({required this.customerId, super.key});
  final int customerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final customer = ref.watch(customerProvider(customerId));
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(customer.value?.name ?? 'جزئیات مشتری'),
          actions: <Widget>[
            IconButton(
              tooltip: 'ویرایش مشتری',
              onPressed: customer.hasValue
                  ? () async {
                      final changed = await context.push<bool>(
                        '/customers/$customerId/edit',
                      );
                      if (changed == true) {
                        ref.invalidate(customerProvider(customerId));
                      }
                    }
                  : null,
              icon: const Icon(Icons.edit_outlined),
            ),
          ],
          bottom: const TabBar(
            tabs: <Widget>[
              Tab(text: 'مشخصات', icon: Icon(Icons.person_outline)),
              Tab(text: 'یادداشت‌ها', icon: Icon(Icons.note_alt_outlined)),
            ],
          ),
        ),
        body: customer.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ErrorState(
            error: error,
            onRetry: () => ref.invalidate(customerProvider(customerId)),
          ),
          data: (item) => TabBarView(
            children: <Widget>[
              _CustomerOverview(customer: item),
              _CustomerNotes(customerId: customerId),
            ],
          ),
        ),
      ),
    );
  }
}

final class _CustomerOverview extends ConsumerWidget {
  const _CustomerOverview({required this.customer});
  final CustomerRecord customer;

  @override
  Widget build(BuildContext context, WidgetRef ref) => RefreshIndicator(
    onRefresh: () async => ref.invalidate(customerProvider(customer.id)),
    child: ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        SectionCard(
          title: customer.name,
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            children: <Widget>[
              Chip(label: Text(labelOf(customerStatuses, customer.status))),
              Chip(label: Text(labelOf(customerIntents, customer.intent))),
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'اطلاعات تماس',
          child: Column(
            children: <Widget>[
              _row('موبایل', customer.mobile),
              _row('تلفن', '${customer.data['phone'] ?? '—'}'),
              _row('ایمیل', '${customer.data['email'] ?? '—'}'),
              _row(
                'روش ترجیحی',
                customer.data['preferred_contact_method'] == 'email'
                    ? 'ایمیل'
                    : 'تلفن',
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'نیاز مشتری',
          child: Column(
            children: <Widget>[
              _row(
                'انواع ملک',
                _types(customer.data['preferred_property_types']),
              ),
              _row(
                'بودجه',
                '${customer.data['budget_min'] ?? '—'} تا ${customer.data['budget_max'] ?? '—'}',
              ),
              _row(
                'محدوده',
                '${customer.data['desired_city'] ?? '—'}، ${customer.data['desired_district'] ?? '—'}',
              ),
              _row(
                'متراژ',
                '${customer.data['min_area_sqm'] ?? '—'} تا ${customer.data['max_area_sqm'] ?? '—'}',
              ),
              _row('حداقل خواب', '${customer.data['min_bedrooms'] ?? '—'}'),
            ],
          ),
        ),
      ],
    ),
  );

  static Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(width: 110, child: Text(label)),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );

  static String _types(Object? value) => value is List && value.isNotEmpty
      ? value.map((item) => labelOf(propertyTypes, '$item')).join('، ')
      : '—';
}

final class _CustomerNotes extends ConsumerStatefulWidget {
  const _CustomerNotes({required this.customerId});
  final int customerId;
  @override
  ConsumerState<_CustomerNotes> createState() => _CustomerNotesState();
}

final class _CustomerNotesState extends ConsumerState<_CustomerNotes> {
  final _body = TextEditingController();
  bool _saving = false;
  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final notes = ref.watch(customerNotesProvider(widget.customerId));
    final userId = ref.watch(authControllerProvider).user?['id'];
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: <Widget>[
              Expanded(
                child: TextField(
                  controller: _body,
                  maxLines: 2,
                  decoration: const InputDecoration(
                    labelText: 'یادداشت جدید',
                    border: OutlineInputBorder(),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                tooltip: 'ثبت یادداشت',
                onPressed: _saving ? null : _add,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 20,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.send_rounded),
              ),
            ],
          ),
        ),
        Expanded(
          child: notes.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) => ErrorState(
              error: error,
              onRetry: () =>
                  ref.invalidate(customerNotesProvider(widget.customerId)),
            ),
            data: (items) => items.isEmpty
                ? const EmptyState(
                    message: 'یادداشتی ثبت نشده است.',
                    icon: Icons.note_alt_outlined,
                  )
                : RefreshIndicator(
                    onRefresh: () async => ref.invalidate(
                      customerNotesProvider(widget.customerId),
                    ),
                    child: ListView.builder(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      itemCount: items.length,
                      itemBuilder: (context, index) {
                        final note = items[index];
                        return Card(
                          child: ListTile(
                            title: Text('${note['body']}'),
                            subtitle: Text('${note['created_at'] ?? ''}'),
                            trailing: note['author_user_id'] == userId
                                ? PopupMenuButton<String>(
                                    onSelected: (action) => action == 'edit'
                                        ? _edit(note)
                                        : _delete(note),
                                    itemBuilder: (_) =>
                                        const <PopupMenuEntry<String>>[
                                          PopupMenuItem(
                                            value: 'edit',
                                            child: Text('ویرایش'),
                                          ),
                                          PopupMenuItem(
                                            value: 'delete',
                                            child: Text('حذف'),
                                          ),
                                        ],
                                  )
                                : null,
                          ),
                        );
                      },
                    ),
                  ),
          ),
        ),
      ],
    );
  }

  Future<void> _add() async {
    final text = _body.text.trim();
    if (text.isEmpty) return;
    setState(() => _saving = true);
    try {
      await ref
          .read(customerRepositoryProvider)
          .addNote(widget.customerId, text);
      _body.clear();
      ref.invalidate(customerNotesProvider(widget.customerId));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
    if (mounted) setState(() => _saving = false);
  }

  Future<void> _edit(Map<String, dynamic> note) async {
    final value = await showDialog<String>(
      context: context,
      builder: (_) => TextInputDialog(
        title: 'ویرایش یادداشت',
        initialValue: '${note['body']}',
        maxLines: 4,
      ),
    );
    if (value == null || value.isEmpty) return;
    try {
      await ref.read(customerRepositoryProvider).patch(
        '/customers/${widget.customerId}/notes/${note['id']}',
        <String, Object?>{'body': value},
      );
      ref.invalidate(customerNotesProvider(widget.customerId));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
  }

  Future<void> _delete(Map<String, dynamic> note) async {
    try {
      await ref
          .read(customerRepositoryProvider)
          .deleteNote(widget.customerId, (note['id'] as num).toInt());
      ref.invalidate(customerNotesProvider(widget.customerId));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
  }
}
