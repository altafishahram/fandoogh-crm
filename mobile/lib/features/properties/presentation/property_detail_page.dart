import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/config/app_config.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/offline/sync_controller.dart';
import 'package:fandoogh_crm/core/phone/phone_launcher.dart';
import 'package:fandoogh_crm/core/storage/android_media_picker.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/widgets/section_card.dart';
import 'package:fandoogh_crm/core/widgets/text_input_dialog.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:fandoogh_crm/features/match_notifications/presentation/related_match_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class PropertyDetailPage extends ConsumerWidget {
  const PropertyDetailPage({required this.propertyId, super.key});
  final int propertyId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final property = ref.watch(propertyProvider(propertyId));
    return DefaultTabController(
      length: 4,
      child: Scaffold(
        appBar: AppBar(
          title: Text(property.value?.title ?? 'جزئیات ملک'),
          actions: <Widget>[
            IconButton(
              tooltip: 'انتشار آگهی',
              onPressed: property.hasValue
                  ? () => context.push('/properties/$propertyId/publication')
                  : null,
              icon: const Icon(Icons.publish_outlined),
            ),
            IconButton(
              tooltip: 'ویرایش ملک',
              onPressed:
                  property.hasValue &&
                      ref.watch(authControllerProvider).can('properties.update')
                  ? () async {
                      final changed = await context.push<bool>(
                        '/properties/$propertyId/edit',
                      );
                      if (changed == true) {
                        ref.invalidate(propertyProvider(propertyId));
                        ref.read(matchDataRevisionProvider.notifier).refresh();
                      }
                    }
                  : null,
              icon: const Icon(Icons.edit_outlined),
            ),
          ],
          bottom: const TabBar(
            isScrollable: true,
            tabs: <Widget>[
              Tab(text: 'مشخصات', icon: Icon(Icons.info_outline)),
              Tab(text: 'یادداشت‌ها', icon: Icon(Icons.note_alt_outlined)),
              Tab(text: 'تصاویر', icon: Icon(Icons.photo_library_outlined)),
              Tab(text: 'تاریخچه', icon: Icon(Icons.history_rounded)),
            ],
          ),
        ),
        body: property.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => ErrorState(
            error: error,
            onRetry: () => ref.invalidate(propertyProvider(propertyId)),
          ),
          data: (item) => TabBarView(
            children: <Widget>[
              _Overview(property: item),
              _PropertyNotes(propertyId: propertyId),
              _PropertyImages(propertyId: propertyId),
              _PropertyHistory(propertyId: propertyId),
            ],
          ),
        ),
      ),
    );
  }
}

final class _Overview extends ConsumerWidget {
  const _Overview({required this.property});
  final PropertyRecord property;

  @override
  Widget build(BuildContext context, WidgetRef ref) => RefreshIndicator(
    onRefresh: () async => ref.invalidate(propertyProvider(property.id)),
    child: ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        SectionCard(
          title: property.title,
          action: FilledButton.tonalIcon(
            onPressed:
                ref
                    .watch(authControllerProvider)
                    .can('properties.change_status')
                ? () => _changeStatus(context, ref)
                : null,
            icon: const Icon(Icons.sync_alt_rounded),
            label: const Text('تغییر وضعیت'),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              Text(
                property.code,
                style: Theme.of(context).textTheme.labelLarge,
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  Chip(label: Text(labelOf(propertyStatuses, property.status))),
                  RelatedMatchBadge(
                    scope: RelatedMatchScope.property(property.id),
                    summary: property.matchSummary,
                  ),
                  Chip(
                    label: Text(labelOf(propertyTypes, property.propertyType)),
                  ),
                  Chip(
                    label: Text(
                      labelOf(transactionTypes, property.transactionType),
                    ),
                  ),
                ],
              ),
              if ((property.data['description'] as String?)?.isNotEmpty ==
                  true) ...<Widget>[
                const Divider(height: 28),
                Text(property.data['description'] as String),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'قیمت و مشخصات',
          child: Column(
            children: <Widget>[
              if (property.transactionType == 'sale') ...<Widget>[
                _row(
                  'قیمت فروش',
                  _money(
                    property.data['sale_price'],
                    property.data['currency_code'],
                  ),
                ),
                if (property.data['delivery_status'] == 'tenant_occupied')
                  _row(
                    'ودیعه مستأجر',
                    _money(
                      property.data['deposit_amount'],
                      property.data['currency_code'],
                    ),
                  ),
                if (property.data['delivery_status'] == 'tenant_occupied')
                  _row(
                    'اجاره ماهانه مستأجر',
                    _money(
                      property.data['monthly_rent'],
                      property.data['currency_code'],
                    ),
                  ),
              ] else ...<Widget>[
                _row(
                  'ودیعه',
                  _money(
                    property.data['deposit_amount'],
                    property.data['currency_code'],
                  ),
                ),
                _row(
                  'اجاره ماهانه',
                  _money(
                    property.data['monthly_rent'],
                    property.data['currency_code'],
                  ),
                ),
              ],
              _row('متراژ', _unit(property.data['area_sqm'], 'مترمربع')),
              if (property.data['building_type'] != null)
                _row(
                  'نوع بنا',
                  labelOf(
                    buildingTypes,
                    property.data['building_type'] as String?,
                  ),
                ),
              if (property.propertyType == 'industrial') ...<Widget>[
                _row('نوع سازه', '${property.data['structure_type'] ?? '—'}'),
                _row(
                  'آب',
                  property.data['has_water'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'برق',
                  property.data['has_electricity'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'گاز',
                  property.data['has_gas'] == true ? 'دارد' : 'ندارد',
                ),
                _row(
                  'تعداد خط تلفن',
                  '${property.data['telephone_line_count'] ?? '—'}',
                ),
                _row('متراژ زمین', '${property.data['land_area'] ?? '—'}'),
                _row('متراژ بنا', '${property.data['building_area'] ?? '—'}'),
              ],
              if (property.transactionType == 'sale')
                _row(
                  'مبلغ هر متر',
                  formatToman(property.data['sale_price_per_sqm']),
                ),
              if (property.transactionType == 'rent' &&
                  property.data['is_convertible'] == true) ...<Widget>[
                _row(
                  'حداقل ودیعه',
                  formatToman(property.data['minimum_deposit']),
                ),
                _row(
                  'حداکثر اجاره',
                  formatToman(property.data['maximum_rent']),
                ),
              ],
              if (property.propertyType != 'land_old_building') ...<Widget>[
                _row('اتاق خواب', '${property.data['bedrooms'] ?? '—'}'),
                _row('سرویس', '${property.data['bathrooms'] ?? '—'}'),
                _row('پارکینگ', '${property.data['parking_spaces'] ?? 0}'),
                _row(
                  'امکانات',
                  <String>[
                        if (property.data['has_storage_room'] == true) 'انباری',
                        if (property.data['has_elevator'] == true) 'آسانسور',
                        if (property.data['has_balcony'] == true) 'بالکن',
                      ].join('، ').isEmpty
                      ? '—'
                      : <String>[
                          if (property.data['has_storage_room'] == true)
                            'انباری',
                          if (property.data['has_elevator'] == true) 'آسانسور',
                          if (property.data['has_balcony'] == true) 'بالکن',
                        ].join('، '),
                ),
              ],
              _row(
                'نوع سرویس',
                _labels(toiletTypes, property.data['toilet_types']),
              ),
              _row(
                'سرویس مستر',
                property.data['has_master_bathroom'] == true ? 'دارد' : 'ندارد',
              ),
              _row(
                'نوع کابینت',
                labelOf(cabinetTypes, property.data['cabinet_type'] as String?),
              ),
              _row(
                'نوع گرمایش',
                labelOf(heatingTypes, property.data['heating_type'] as String?),
              ),
              _row(
                'نوع سرمایش',
                labelOf(coolingTypes, property.data['cooling_type'] as String?),
              ),
              _row(
                'نوع کف‌پوش',
                labelOf(
                  flooringTypes,
                  property.data['flooring_type'] as String?,
                ),
              ),
              _row(
                'وضعیت بازسازی',
                labelOf(
                  renovationStatuses,
                  property.data['renovation_status'] as String?,
                ),
              ),
              _row(
                'جهت ساختمان',
                labelOf(
                  buildingOrientations,
                  property.data['building_orientation'] as String?,
                ),
              ),
              _row(
                'نوع سند',
                labelOf(deedTypes, property.data['deed_type'] as String?),
              ),
              _row('امکانات تکمیلی', _extraFeatures(property.data)),
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'نشانی',
          child: Column(
            children: <Widget>[
              _row('شهر', property.city),
              _row('محله', '${property.data['district'] ?? '—'}'),
              _row('نشانی', property.address),
              _row('پلاک', '${property.data['plaque'] ?? '—'}'),
              _row(
                'وضعیت تخلیه',
                labelOf(
                  deliveryStatuses,
                  property.data['delivery_status'] as String?,
                ),
              ),
              if (property.transactionType == 'sale' &&
                  property.data['delivery_status'] == 'ready' &&
                  property.data['available_from'] != null)
                _row(
                  'تاریخ آماده تحویل',
                  PersianDate.formatIso('${property.data['available_from']}'),
                )
              else if (property.data['evacuation_date'] != null)
                _row(
                  'تاریخ تخلیه',
                  PersianDate.formatIso('${property.data['evacuation_date']}'),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        SectionCard(
          title: 'مالکان',
          child: Column(
            children: property.owners
                .map(
                  (owner) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const CircleAvatar(
                      child: Icon(Icons.badge_outlined),
                    ),
                    title: Text(
                      '${owner['full_name'] ?? owner['display_name'] ?? ''}',
                    ),
                    subtitle: Text(
                      <String>[
                        if ('${owner['mobile'] ?? ''}'.isNotEmpty)
                          '${owner['mobile']}',
                        if ('${owner['phone'] ?? ''}'.isNotEmpty)
                          '${owner['phone']}',
                      ].join(' • '),
                    ),
                    trailing: Wrap(
                      spacing: 2,
                      children: <Widget>[
                        if ('${owner['mobile'] ?? ''}'.isNotEmpty)
                          IconButton(
                            tooltip: 'تماس با همراه مالک',
                            onPressed: () =>
                                launchPhoneCall(context, '${owner['mobile']}'),
                            icon: const Icon(Icons.phone_rounded),
                          ),
                        if ('${owner['phone'] ?? ''}'.isNotEmpty)
                          IconButton(
                            tooltip: 'تماس با تلفن ثابت مالک',
                            onPressed: () =>
                                launchPhoneCall(context, '${owner['phone']}'),
                            icon: const Icon(Icons.call_outlined),
                          ),
                      ],
                    ),
                  ),
                )
                .toList(growable: false),
          ),
        ),
      ],
    ),
  );

  Future<void> _changeStatus(BuildContext context, WidgetRef ref) async {
    final result = await showDialog<_StatusChange>(
      context: context,
      builder: (_) => _StatusChangeDialog(initialStatus: property.status),
    );
    if (result == null || result.status == property.status) return;
    try {
      await ref
          .read(propertyRepositoryProvider)
          .changeStatus(
            property.id,
            status: result.status,
            expectedUpdatedAt: property.updatedAt,
            expectedVersion: property.lockVersion,
            reason: result.reason.isEmpty ? null : result.reason,
          );
      ref.invalidate(propertyProvider(property.id));
      ref.invalidate(propertiesProvider);
      ref.read(matchDataRevisionProvider.notifier).refresh();
      await ref.read(syncControllerProvider.notifier).refreshQueue();
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('اطلاعات با موفقیت به‌روزرسانی شد')),
        );
      }
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
  }

  static Widget _row(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 5),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        SizedBox(width: 120, child: Text(label)),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );

  static String _labels(List<ChoiceItem> choices, Object? value) {
    if (value is! List || value.isEmpty) return '—';
    return value.map((item) => labelOf(choices, '$item')).join('، ');
  }

  static String _extraFeatures(Map<String, dynamic> data) {
    final labels = <String>[
      if (data['has_loan'] == true) 'وام',
      if (data['is_exchangeable'] == true) 'قابل معاوضه',
      if (data['has_pool'] == true) 'استخر',
      if (data['has_jacuzzi'] == true) 'جکوزی',
      if (data['has_sauna'] == true) 'سونا',
    ];
    return labels.isEmpty ? '—' : labels.join('، ');
  }

  static String _money(Object? value, Object? currency) => formatToman(value);
  static String _unit(Object? value, String unit) =>
      value == null ? '—' : '$value $unit';
}

typedef _StatusChange = ({String status, String reason});

final class _StatusChangeDialog extends StatefulWidget {
  const _StatusChangeDialog({required this.initialStatus});

  final String initialStatus;

  @override
  State<_StatusChangeDialog> createState() => _StatusChangeDialogState();
}

final class _StatusChangeDialogState extends State<_StatusChangeDialog> {
  late String _status;
  late final TextEditingController _reason;

  @override
  void initState() {
    super.initState();
    _status = widget.initialStatus;
    _reason = TextEditingController();
  }

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('تغییر وضعیت ملک'),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        ChoiceField(
          value: _status,
          label: 'وضعیت جدید',
          items: propertyStatuses,
          onChanged: (value) => setState(() => _status = value!),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _reason,
          maxLines: 2,
          decoration: const InputDecoration(
            labelText: 'دلیل (اختیاری)',
            border: OutlineInputBorder(),
          ),
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
          status: _status,
          reason: _reason.text.trim(),
        )),
        child: const Text('ثبت'),
      ),
    ],
  );
}

final class _PropertyNotes extends ConsumerStatefulWidget {
  const _PropertyNotes({required this.propertyId});
  final int propertyId;

  @override
  ConsumerState<_PropertyNotes> createState() => _PropertyNotesState();
}

final class _PropertyNotesState extends ConsumerState<_PropertyNotes> {
  final _body = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final notes = ref.watch(propertyNotesProvider(widget.propertyId));
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
                  ref.invalidate(propertyNotesProvider(widget.propertyId)),
            ),
            data: (items) => items.isEmpty
                ? const EmptyState(
                    message: 'یادداشتی ثبت نشده است.',
                    icon: Icons.note_alt_outlined,
                  )
                : RefreshIndicator(
                    onRefresh: () async => ref.invalidate(
                      propertyNotesProvider(widget.propertyId),
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
          .read(propertyRepositoryProvider)
          .addNote(widget.propertyId, text);
      _body.clear();
      ref.invalidate(propertyNotesProvider(widget.propertyId));
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
      await ref.read(propertyRepositoryProvider).patch(
        '/properties/${widget.propertyId}/notes/${note['id']}',
        <String, Object?>{'body': value},
      );
      ref.invalidate(propertyNotesProvider(widget.propertyId));
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
          .read(propertyRepositoryProvider)
          .deleteNote(widget.propertyId, (note['id'] as num).toInt());
      ref.invalidate(propertyNotesProvider(widget.propertyId));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
  }
}

final class _PropertyImages extends ConsumerStatefulWidget {
  const _PropertyImages({required this.propertyId});
  final int propertyId;

  @override
  ConsumerState<_PropertyImages> createState() => _PropertyImagesState();
}

final class _PropertyImagesState extends ConsumerState<_PropertyImages> {
  String? _pendingPath;
  double? _progress;
  String? _uploadError;

  @override
  Widget build(BuildContext context) {
    final images = ref.watch(propertyImagesProvider(widget.propertyId));
    ref.watch(authControllerProvider);
    final token = ref.read(apiClientProvider).token;
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              FilledButton.icon(
                onPressed: _progress == null ? _pick : null,
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: const Text('افزودن تصویر'),
              ),
              if (_progress != null) ...<Widget>[
                const SizedBox(height: 8),
                LinearProgressIndicator(
                  value: _progress == 0 ? null : _progress,
                ),
                const SizedBox(height: 4),
                Text('${((_progress ?? 0) * 100).round()}٪ بارگذاری'),
              ],
              if (_uploadError != null) ...<Widget>[
                const SizedBox(height: 8),
                Row(
                  children: <Widget>[
                    Expanded(
                      child: Text(
                        _uploadError!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: _retry,
                      icon: const Icon(Icons.refresh),
                      label: const Text('تلاش دوباره'),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
        Expanded(
          child: images.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (error, _) => ErrorState(
              error: error,
              onRetry: () =>
                  ref.invalidate(propertyImagesProvider(widget.propertyId)),
            ),
            data: (items) => items.isEmpty
                ? const EmptyState(
                    message: 'تصویری برای این ملک ثبت نشده است.',
                    icon: Icons.photo_library_outlined,
                  )
                : _PropertyImageGallery(
                    items: items,
                    token: token,
                    onShowFullImage: _showFullImage,
                    onDelete: _delete,
                  ),
          ),
        ),
      ],
    );
  }

  Future<void> _pick() async {
    try {
      final path = await AndroidMediaPicker.pickImage();
      if (path == null) return;
      _pendingPath = path;
      await _upload();
    } catch (error) {
      if (mounted) setState(() => _uploadError = 'انتخاب تصویر انجام نشد.');
    }
  }

  Future<void> _retry() => _upload();

  Future<void> _upload() async {
    final path = _pendingPath;
    if (path == null) return;
    setState(() {
      _progress = 0;
      _uploadError = null;
    });
    try {
      await ref
          .read(propertyRepositoryProvider)
          .addImage(
            widget.propertyId,
            path,
            onProgress: (sent, total) {
              if (mounted && total > 0) {
                setState(() => _progress = sent / total);
              }
            },
          );
      _pendingPath = null;
      ref.invalidate(propertyImagesProvider(widget.propertyId));
      if (mounted) setState(() => _progress = null);
    } catch (error) {
      if (mounted) {
        setState(() {
          _progress = null;
          _uploadError = apiFailureFrom(error).message;
        });
      }
    }
  }

  Future<void> _delete(int id) async {
    try {
      await ref
          .read(propertyRepositoryProvider)
          .deleteImage(widget.propertyId, id);
      ref.invalidate(propertyImagesProvider(widget.propertyId));
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(apiFailureFrom(error).message)));
      }
    }
  }

  Future<void> _showFullImage(String imageUrl, Map<String, String>? headers) =>
      showDialog<void>(
        context: context,
        barrierColor: Colors.black,
        builder: (dialogContext) => Dialog.fullscreen(
          backgroundColor: Colors.black,
          child: Scaffold(
            backgroundColor: Colors.black,
            appBar: AppBar(
              backgroundColor: Colors.black,
              foregroundColor: Colors.white,
              title: const Text('تصویر ملک'),
              leading: IconButton(
                tooltip: 'بستن',
                onPressed: () => Navigator.pop(dialogContext),
                icon: const Icon(Icons.close_rounded),
              ),
            ),
            body: InteractiveViewer(
              minScale: 0.8,
              maxScale: 5,
              child: Center(
                child: Image.network(
                  imageUrl,
                  headers: headers,
                  width: double.infinity,
                  height: double.infinity,
                  fit: BoxFit.contain,
                  errorBuilder: (_, _, _) => const Column(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      Icon(
                        Icons.broken_image_outlined,
                        color: Colors.white,
                        size: 48,
                      ),
                      SizedBox(height: 12),
                      Text(
                        'نمایش تصویر امکان‌پذیر نیست.',
                        style: TextStyle(color: Colors.white),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      );
}

final class _PropertyImageGallery extends StatefulWidget {
  const _PropertyImageGallery({
    required this.items,
    required this.token,
    required this.onShowFullImage,
    required this.onDelete,
  });

  final List<Map<String, dynamic>> items;
  final String? token;
  final Future<void> Function(String, Map<String, String>?) onShowFullImage;
  final Future<void> Function(int) onDelete;

  @override
  State<_PropertyImageGallery> createState() => _PropertyImageGalleryState();
}

final class _PropertyImageGalleryState extends State<_PropertyImageGallery> {
  late final PageController _pageController;
  int _activeIndex = 0;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
  }

  @override
  void didUpdateWidget(covariant _PropertyImageGallery oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.items.isEmpty) return;
    if (_activeIndex >= widget.items.length) {
      _activeIndex = widget.items.length - 1;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _pageController.hasClients) {
          _pageController.jumpToPage(_activeIndex);
        }
      });
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final headers = widget.token == null
        ? null
        : <String, String>{'Authorization': 'Bearer ${widget.token}'};
    return Column(
      children: <Widget>[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Row(
            children: widget.items
                .asMap()
                .entries
                .map(
                  (entry) => Expanded(
                    child: AnimatedContainer(
                      key: ValueKey('property-image-indicator-${entry.key}'),
                      duration: const Duration(milliseconds: 180),
                      height: 4,
                      margin: EdgeInsets.only(
                        left: entry.key == 0 ? 0 : 3,
                        right: entry.key == widget.items.length - 1 ? 0 : 3,
                      ),
                      decoration: BoxDecoration(
                        color: entry.key == _activeIndex
                            ? theme.colorScheme.primary
                            : theme.colorScheme.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(99),
                      ),
                    ),
                  ),
                )
                .toList(growable: false),
          ),
        ),
        Expanded(
          child: PageView.builder(
            controller: _pageController,
            itemCount: widget.items.length,
            onPageChanged: (index) => setState(() => _activeIndex = index),
            itemBuilder: (context, index) {
              final item = widget.items[index];
              final imageUrl = _contentUrl('${item['content_url']}');
              return Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 12),
                child: Card(
                  clipBehavior: Clip.antiAlias,
                  child: Stack(
                    fit: StackFit.expand,
                    children: <Widget>[
                      Semantics(
                        button: true,
                        label: 'نمایش تصویر کامل',
                        child: InkWell(
                          onTap: () =>
                              widget.onShowFullImage(imageUrl, headers),
                          child: Image.network(
                            imageUrl,
                            headers: headers,
                            fit: BoxFit.contain,
                            errorBuilder: (_, _, _) => const Center(
                              child: Icon(Icons.broken_image_outlined),
                            ),
                          ),
                        ),
                      ),
                      Positioned(
                        top: 8,
                        left: 8,
                        child: IconButton.filledTonal(
                          tooltip: 'حذف تصویر',
                          onPressed: () =>
                              widget.onDelete((item['id'] as num).toInt()),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ),
                      if (item['is_cover'] == true)
                        const Positioned(
                          right: 8,
                          bottom: 8,
                          child: Chip(label: Text('کاور')),
                        ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  static String _contentUrl(String value) {
    final uri = Uri.tryParse(value);
    final base = Uri.parse(AppConfig.apiBaseUrl);
    if (uri == null) return value;
    if (uri.host == 'localhost' || uri.host == '127.0.0.1') {
      return uri
          .replace(scheme: base.scheme, host: base.host, port: base.port)
          .toString();
    }
    return value;
  }
}

final class _PropertyHistory extends ConsumerWidget {
  const _PropertyHistory({required this.propertyId});
  final int propertyId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final history = ref.watch(propertyHistoryProvider(propertyId));
    return history.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, _) => ErrorState(
        error: error,
        onRetry: () => ref.invalidate(propertyHistoryProvider(propertyId)),
      ),
      data: (items) => items.isEmpty
          ? const EmptyState(
              message: 'تاریخچه‌ای وجود ندارد.',
              icon: Icons.history_rounded,
            )
          : RefreshIndicator(
              onRefresh: () async =>
                  ref.invalidate(propertyHistoryProvider(propertyId)),
              child: ListView.builder(
                padding: const EdgeInsets.all(12),
                itemCount: items.length,
                itemBuilder: (context, index) {
                  final item = items[index];
                  return ListTile(
                    leading: const CircleAvatar(
                      child: Icon(Icons.history_rounded),
                    ),
                    title: Text(_historyLabel('${item['action']}')),
                    subtitle: Text(
                      <String>[
                        if (item['from_status'] != null ||
                            item['to_status'] != null)
                          '${labelOf(propertyStatuses, item['from_status'] as String?)} ← ${labelOf(propertyStatuses, item['to_status'] as String?)}',
                        if (item['reason'] != null) '${item['reason']}',
                        PersianDate.formatIso('${item['occurred_at'] ?? ''}'),
                      ].join('\n'),
                    ),
                  );
                },
              ),
            ),
    );
  }

  static String _historyLabel(String action) => switch (action) {
    'created' => 'ایجاد ملک',
    'updated' => 'ویرایش مشخصات',
    'status_changed' => 'تغییر وضعیت',
    'images_changed' => 'تغییر تصاویر',
    'owners_changed' => 'تغییر مالکان',
    'assignment_changed' => 'تغییر کارشناس',
    'archived' => 'بایگانی',
    _ => action,
  };
}
