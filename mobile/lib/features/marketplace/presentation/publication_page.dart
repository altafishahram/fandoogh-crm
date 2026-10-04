import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/features/marketplace/data/marketplace_repository.dart';
import 'package:fandoogh_crm/features/marketplace/presentation/marketplace_pages.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class PublicationPage extends ConsumerStatefulWidget {
  const PublicationPage({required this.propertyId, super.key});
  final int propertyId;
  @override
  ConsumerState<PublicationPage> createState() => _PublicationPageState();
}

final class _PublicationPageState extends ConsumerState<PublicationPage> {
  final _title = TextEditingController(),
      _description = TextEditingController();
  Map<String, dynamic>? _data;
  Object? _error;
  bool _agency = false, _public = false, _saving = false;
  int? _advisor;
  final Set<int> _images = {};
  @override
  void initState() {
    super.initState();
    _title.addListener(_previewChanged);
    _description.addListener(_previewChanged);
    _load();
  }

  void _previewChanged() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final data = await ref
          .read(marketplaceRepositoryProvider)
          .publication(widget.propertyId);
      if (!mounted) return;
      setState(() {
        _data = data;
        _title.text = '${data['public_title'] ?? ''}';
        _description.text = '${data['public_description'] ?? ''}';
        _agency = data['share_with_agencies'] == true;
        _public = data['publish_public'] == true;
        _advisor = (data['responding_user_id'] as num?)?.toInt();
        _images.addAll(
          (data['image_ids'] as List? ?? []).whereType<num>().map(
            (n) => n.toInt(),
          ),
        );
      });
    } catch (e) {
      if (mounted) setState(() => _error = e);
    }
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(marketplaceRepositoryProvider)
          .savePublication(widget.propertyId, {
            'share_with_agencies': _agency,
            'publish_public': _public,
            'public_title': _title.text.trim(),
            'public_description': _description.text.trim(),
            'image_ids': _images.toList(),
            if (_advisor != null) 'responding_user_id': _advisor,
            'expected_version': _data?['version'] ?? 0,
          });
      if (!mounted) return;
      setState(() => _data = data);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text('تنظیمات انتشار ذخیره شد.')));
    } catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    if (data == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('انتشار آگهی')),
        body: _error != null
            ? ErrorState(error: _error!, onRetry: _load)
            : const Center(child: CircularProgressIndicator()),
      );
    }
    final canPublish = data['can_publish'] == true;
    final advisors = (data['advisors'] as List? ?? [])
        .whereType<Map>()
        .toList();
    return Scaffold(
      appBar: AppBar(title: const Text('انتشار آگهی')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'دو مقصد انتشار مستقل هستند. فقط آژانس تأییدشده و مدیر یا مشاور دارای مجوز می‌تواند منتشر کند.',
          ),
          if (!canPublish)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 16),
              child: Text('اجازه انتشار ندارید یا آژانس تأیید نشده است.'),
            ),
          SwitchListTile(
            title: const Text('همکاری با آژانس‌ها'),
            value: _agency,
            onChanged: canPublish && !_saving
                ? (v) => setState(() => _agency = v)
                : null,
          ),
          SwitchListTile(
            title: const Text('انتشار برای عموم'),
            value: _public,
            onChanged: canPublish && !_saving
                ? (v) => setState(() => _public = v)
                : null,
          ),
          TextField(
            controller: _title,
            enabled: canPublish && !_saving,
            decoration: const InputDecoration(labelText: 'عنوان قابل انتشار'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _description,
            enabled: canPublish && !_saving,
            maxLines: 4,
            decoration: const InputDecoration(
              labelText: 'توضیحات قابل انتشار',
              helperText:
                  'نام یا شماره مالک، نشانی دقیق و اطلاعات خصوصی ننویسید.',
            ),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: advisors.any((a) => a['id'] == _advisor)
                ? _advisor
                : null,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'مشاور پاسخ‌گو'),
            items: advisors
                .map(
                  (a) => DropdownMenuItem<int>(
                    value: (a['id'] as num).toInt(),
                    child: Text('${a['name']}'),
                  ),
                )
                .toList(),
            onChanged: data['can_change_advisor'] == true && !_saving
                ? (v) => setState(() => _advisor = v)
                : null,
          ),
          const SizedBox(height: 16),
          const Text(
            'تصاویر انتخاب‌شده منتشر می‌شوند؛ تصویر سند یا اطلاعات مالک را انتخاب نکنید.',
          ),
          ...(data['candidate_images'] as List? ?? []).whereType<Map>().map((
            i,
          ) {
            final id = (i['id'] as num).toInt();
            return CheckboxListTile(
              value: _images.contains(id),
              title: AuthenticatedPhoto('${i['url']}', height: 140),
              onChanged: canPublish && !_saving
                  ? (v) => setState(() {
                      if (v == true) {
                        _images.add(id);
                      } else {
                        _images.remove(id);
                      }
                    })
                  : null,
            );
          }),
          const SizedBox(height: 16),
          ExpansionTile(
            title: const Text('پیش‌نمایش محتوای انتشار'),
            children: [
              ListTile(
                title: Text(_title.text),
                subtitle: Text(_description.text),
              ),
              const ListTile(title: Text('آدرس: فقط محله · تماس: آژانس')),
            ],
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                apiFailureFrom(_error!).message,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          FilledButton(
            onPressed: canPublish && !_saving ? _save : null,
            child: Text(_saving ? 'در حال ذخیره…' : 'ذخیره تنظیمات انتشار'),
          ),
        ],
      ),
    );
  }
}
