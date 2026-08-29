import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/phone/phone_launcher.dart';
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
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(customer.value?.name ?? 'جزئیات مشتری'),
          actions: <Widget>[
            IconButton(
              tooltip: 'ویرایش مشتری',
              onPressed:
                  customer.hasValue &&
                      ref.watch(authControllerProvider).can('customers.update')
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
            isScrollable: true,
            tabs: <Widget>[
              Tab(text: 'مشخصات', icon: Icon(Icons.person_outline)),
              Tab(text: 'یادداشت‌ها', icon: Icon(Icons.note_alt_outlined)),
              Tab(text: 'تاریخچه', icon: Icon(Icons.history_rounded)),
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
              _CustomerHistory(customerId: customerId),
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
              _phoneRow(context, 'موبایل', customer.mobile),
              _phoneRow(
                context,
                'تلفن ثابت',
                '${customer.data['phone'] ?? ''}',
              ),
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
              if (customer.data['building_type'] != null)
                _row(
                  'نوع بنا',
                  labelOf(
                    buildingTypes,
                    customer.data['building_type'] as String?,
                  ),
                ),
              if (customer.data['desired_property_type'] ==
                  'industrial') ...<Widget>[
                _row('نوع سازه', '${customer.data['structure_type'] ?? '—'}'),
                _row(
                  'آب',
                  customer.data['has_water'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'برق',
                  customer.data['has_electricity'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'گاز',
                  customer.data['has_gas'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'تعداد خط تلفن',
                  '${customer.data['telephone_line_count'] ?? '—'}',
                ),
                _row(
                  'متراژ زمین',
                  _areaRange(
                    customer.data['land_area_min'] ?? customer.data['land_area'],
                    customer.data['land_area_max'] ?? customer.data['land_area'],
                  ),
                ),
                _row(
                  'متراژ بنا',
                  _areaRange(
                    customer.data['building_area_min'] ?? customer.data['building_area'],
                    customer.data['building_area_max'] ?? customer.data['building_area'],
                  ),
                ),
              ],
              if (customer.intent == 'buy')
                _row(
                  'بودجه',
                  '${formatToman(customer.data['budget_min'])} تا ${formatToman(customer.data['budget_max'])}',
                )
              else ...<Widget>[
                _row(
                  'ودیعه',
                  '${formatToman(customer.data['rental_deposit_min'])} تا ${formatToman(customer.data['rental_deposit_max'])}',
                ),
                _row(
                  'اجاره',
                  '${formatToman(customer.data['rental_rent_min'])} تا ${formatToman(customer.data['rental_rent_max'])}',
                ),
                _row(
                  'تبدیل',
                  customer.data['accepts_rent_conversion'] == true
                      ? 'دارد'
                      : 'ندارد',
                ),
              ],
              _row(
                'محدوده',
                '${customer.data['desired_city'] ?? '—'}، ${customer.data['desired_district'] ?? '—'}',
              ),
              _row(
                'متراژ',
                '${customer.data['min_area_sqm'] ?? '—'} تا ${customer.data['max_area_sqm'] ?? '—'}',
              ),
              _row('حداقل خواب', '${customer.data['min_bedrooms'] ?? '—'}'),
              _row(
                'پارکینگ',
                _yesNo(
                  customer.data['has_parking'] ??
                      ((num.tryParse(
                                '${customer.data['min_parking_spaces'] ?? ''}',
                              ) ??
                              0) >
                          0),
                ),
              ),
              _row('انباری', _yesNo(customer.data['has_storage_room'])),
              if (customer.intent == 'rent') ...<Widget>[
                _row(
                  'مالک در ساختمان سکونت دارد',
                  _yesNo(customer.data['owner_resides']),
                ),
                _row('آسانسور', _yesNo(customer.data['has_elevator'])),
                _row('بالکن', _yesNo(customer.data['has_balcony'])),
              ],
              const Divider(height: 22),
              _row(
                'نوع سرویس',
                _labels(toiletTypes, customer.data['toilet_types']),
              ),
              if (customer.intent != 'rent' ||
                  customer.data['desired_property_type'] == 'apartment')
                _row(
                  'سرویس مستر',
                  _yesNo(customer.data['has_master_bathroom']),
                ),
              _row(
                'نوع کابینت',
                labelOf(cabinetTypes, customer.data['cabinet_type'] as String?),
              ),
              _row(
                'نوع گرمایش',
                labelOf(heatingTypes, customer.data['heating_type'] as String?),
              ),
              _row(
                'نوع سرمایش',
                labelOf(coolingTypes, customer.data['cooling_type'] as String?),
              ),
              _row(
                'نوع کف‌پوش',
                labelOf(
                  flooringTypes,
                  customer.data['flooring_type'] as String?,
                ),
              ),
              _row(
                'بازسازی',
                labelOf(
                  renovationStatuses,
                  customer.data['renovation_status'] as String?,
                ),
              ),
              _row(
                'جهت ساختمان',
                labelOf(
                  buildingOrientations,
                  customer.data['building_orientation'] as String?,
                ),
              ),
              if (customer.intent != 'rent') ...<Widget>[
                _row(
                  'نوع سند',
                  labelOf(deedTypes, customer.data['deed_type'] as String?),
                ),
                _row('وام', _yesNo(customer.data['has_loan'])),
                _row('قابل معاوضه', _yesNo(customer.data['is_exchangeable'])),
                _row('استخر', _yesNo(customer.data['has_pool'])),
                _row('جکوزی', _yesNo(customer.data['has_jacuzzi'])),
                _row('سونا', _yesNo(customer.data['has_sauna'])),
              ],
              _row('تاریخ ثبت', PersianDate.formatIso(customer.createdAt)),
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

  static Widget _phoneRow(BuildContext context, String label, String number) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: <Widget>[
            SizedBox(width: 110, child: Text(label)),
            Expanded(
              child: Text(
                number.isEmpty ? '—' : number,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
            if (number.isNotEmpty)
              IconButton(
                tooltip: 'تماس با $label مشتری',
                onPressed: () => launchPhoneCall(context, number),
                icon: const Icon(Icons.phone_rounded),
              ),
          ],
        ),
      );

  static String _types(Object? value) => value is List && value.isNotEmpty
      ? value.map((item) => labelOf(propertyTypes, '$item')).join('، ')
      : '—';

  static String _labels(List<ChoiceItem> items, Object? value) =>
      value is List && value.isNotEmpty
      ? value.map((item) => labelOf(items, '$item')).join('، ')
      : '—';

  static String _yesNo(Object? value) => value == true ? 'بله' : 'خیر';

  static String _areaRange(Object? minimum, Object? maximum) {
    final min = '$minimum'.trim();
    final max = '$maximum'.trim();
    if ((minimum == null || min.isEmpty || min == 'null') &&
        (maximum == null || max.isEmpty || max == 'null')) {
      return '—';
    }
    if (min == max || maximum == null || max.isEmpty || max == 'null') {
      return min;
    }
    return '$min تا $max';
  }
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
                            subtitle: Text(
                              PersianDate.formatIso(
                                '${note['created_at'] ?? ''}',
                              ),
                            ),
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

final class _CustomerHistory extends ConsumerWidget {
  const _CustomerHistory({required this.customerId});
  final int customerId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final history = ref.watch(customerHistoryProvider(customerId));
    return history.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => ErrorState(
        error: error,
        onRetry: () => ref.invalidate(customerHistoryProvider(customerId)),
      ),
      data: (items) => items.isEmpty
          ? const EmptyState(
              message: 'تاریخچه‌ای برای این مشتری وجود ندارد.',
              icon: Icons.history_rounded,
            )
          : RefreshIndicator(
              onRefresh: () async =>
                  ref.invalidate(customerHistoryProvider(customerId)),
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: items.length,
                separatorBuilder: (_, _) => const SizedBox(height: 8),
                itemBuilder: (context, index) {
                  final item = items[index];
                  return Card(
                    child: ListTile(
                      leading: const Icon(Icons.history_toggle_off_rounded),
                      title: Text(_label('${item['action'] ?? ''}')),
                      subtitle: Text(
                        PersianDate.formatIso(
                          '${item['occurred_at'] ?? item['created_at'] ?? ''}',
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }

  static String _label(String action) => switch (action) {
    'created' => 'ایجاد مشتری',
    'updated' => 'ویرایش مشخصات',
    'status_changed' => 'تغییر وضعیت',
    _ => 'تغییر اطلاعات',
  };
}
