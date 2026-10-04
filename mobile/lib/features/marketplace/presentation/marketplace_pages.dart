import 'dart:async';
import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/widgets/choice_field.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/config/app_config.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/core/phone/phone_launcher.dart';
import 'package:fandoogh_crm/core/widgets/async_content.dart';
import 'package:fandoogh_crm/features/geography/geography.dart';
import 'package:fandoogh_crm/features/marketplace/data/marketplace_repository.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AuthenticatedPhoto extends ConsumerWidget {
  const AuthenticatedPhoto(this.url, {this.height = 220, super.key});
  final String url;
  final double height;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final target = Uri.parse(url);
    if (target.origin != Uri.parse(AppConfig.apiBaseUrl).origin) {
      return SizedBox(
        height: height,
        child: const Center(child: Icon(Icons.image_not_supported_outlined)),
      );
    }
    return Image.network(
      url,
      headers: {
        'Authorization': 'Bearer ${ref.watch(apiClientProvider).token ?? ''}',
      },
      height: height,
      width: double.infinity,
      fit: BoxFit.cover,
      loadingBuilder: (_, child, progress) => progress == null
          ? child
          : SizedBox(
              height: height,
              child: const Center(child: CircularProgressIndicator()),
            ),
      errorBuilder: (_, _, _) => SizedBox(
        height: height,
        child: const Center(
          child: Icon(
            Icons.image_not_supported_outlined,
            semanticLabel: 'تصویر در دسترس نیست',
          ),
        ),
      ),
    );
  }
}

final class MarketplacePage extends ConsumerStatefulWidget {
  const MarketplacePage({this.publicOnly = false, super.key});
  final bool publicOnly;
  @override
  ConsumerState<MarketplacePage> createState() => _MarketplacePageState();
}

final class _MarketplacePageState extends ConsumerState<MarketplacePage>
    with WidgetsBindingObserver {
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _load();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  List<Listing> _items = [];
  RegionSelection _region = const RegionSelection();
  String _audience = 'public';
  int _page = 1, _revision = 0;
  bool _busy = false, _hasMore = false;
  Object? _error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (!widget.publicOnly) _region = agencyRegion(ref);
    _load();
  }

  Future<void> _load({bool more = false}) async {
    final revision = ++_revision;
    final page = more ? _page + 1 : 1;
    setState(() {
      _busy = true;
      _error = null;
      if (!more) _items = [];
    });
    try {
      final result = await ref
          .read(marketplaceRepositoryProvider)
          .listings(_audience, _region.toJson(), page);
      if (!mounted || revision != _revision) return;
      setState(() {
        _items = more ? [..._items, ...result.items] : result.items;
        _page = page;
        _hasMore = result.hasMore;
        _busy = false;
      });
    } catch (e) {
      if (mounted && revision == _revision) {
        setState(() {
          _error = e;
          _busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('آگهی‌های املاک'),
      actions: [
        IconButton(
          tooltip: 'گفت‌وگوها',
          onPressed: () => context.push('/conversations'),
          icon: const Icon(Icons.chat_bubble_outline),
        ),
        if (widget.publicOnly)
          IconButton(
            tooltip: 'حساب و خروج',
            onPressed: () => context.push('/account'),
            icon: const Icon(Icons.person_outline),
          ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          if (!widget.publicOnly)
            SegmentedButton<String>(
              segments: [
                ButtonSegment(value: 'public', label: Text('عمومی')),
                ButtonSegment(
                  value: 'agency',
                  label: Text('همکاری آژانس‌ها'),
                  enabled:
                      ref.watch(authControllerProvider).user?['capabilities']
                          is Map &&
                      (ref.watch(authControllerProvider).user?['capabilities']
                              as Map)['can_browse_agency_marketplace'] ==
                          true,
                ),
              ],
              selected: {_audience},
              onSelectionChanged: (values) {
                setState(() => _audience = values.first);
                _load();
              },
            ),
          ExpansionTile(
            title: const Text('انتخاب منطقه'),
            subtitle: const Text('استان، شهرستان و شهر قابل تغییر هستند'),
            children: [
              RegionFields(
                value: _region,
                onChanged: (v) {
                  setState(() => _region = v);
                  _load();
                },
              ),
              TextButton(
                onPressed: () {
                  setState(() => _region = const RegionSelection());
                  _load();
                },
                child: const Text('تمام ایران'),
              ),
            ],
          ),
          const Text('آگهی‌ها و گفت‌وگوها به اینترنت نیاز دارند.'),
          if (_error != null) ErrorState(error: _error!, onRetry: _load),
          if (!_busy && _error == null && _items.isEmpty)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Text(
                'آگهی‌ای برای این منطقه موجود نیست.',
                textAlign: TextAlign.center,
              ),
            ),
          ..._items.map(
            (item) => Card(
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () =>
                    context.push('/marketplace/${item.id}?audience=$_audience'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (item.cover != null)
                      AuthenticatedPhoto(item.cover!, height: 170),
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.title,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          Text(
                            '${labelOf(propertyTypes, item.propertyType)} · ${labelOf(transactionTypes, item.transactionType)}',
                          ),
                          Text('محله: ${item.neighborhood}'),
                          Text(
                            persianDigits(
                              '${item.area} متر · ${item.bedrooms} اتاق · ساخت ${item.yearBuilt}',
                            ),
                          ),
                          Text(item.agencyName),
                          ...listingPriceLabels(item).map(Text.new),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (_busy) const Center(child: CircularProgressIndicator()),
          if (_hasMore && !_busy)
            TextButton(
              onPressed: () => _load(more: true),
              child: const Text('نمایش بیشتر'),
            ),
        ],
      ),
    ),
  );
}

final class ListingDetailPage extends ConsumerStatefulWidget {
  const ListingDetailPage({
    required this.id,
    required this.audience,
    super.key,
  });
  final int id;
  final String audience;
  @override
  ConsumerState<ListingDetailPage> createState() => _ListingDetailPageState();
}

final class _ListingDetailPageState extends ConsumerState<ListingDetailPage>
    with WidgetsBindingObserver {
  Timer? _timer;
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _timer ??= Timer.periodic(const Duration(seconds: 30), (_) {
        if (mounted) {
          setState(_reload);
        }
      });
      setState(_reload);
    } else {
      _timer?.cancel();
      _timer = null;
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  late Future<Listing> _future;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _timer = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) {
        setState(_reload);
      }
    });
    _reload();
  }

  void _reload() => _future = ref
      .read(marketplaceRepositoryProvider)
      .listing(widget.id, widget.audience);
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('جزئیات آگهی')),
    body: FutureBuilder<Listing>(
      future: _future,
      builder: (_, snapshot) {
        if (snapshot.hasError) {
          return ErrorState(
            error: snapshot.error!,
            onRetry: () => setState(_reload),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final item = snapshot.requireData;
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            ...item.images.map(
              (url) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: AuthenticatedPhoto(url),
              ),
            ),
            Text(item.title, style: Theme.of(context).textTheme.headlineSmall),
            Text('محله: ${item.neighborhood}'),
            Text(
              '${labelOf(propertyTypes, item.propertyType)} · ${labelOf(transactionTypes, item.transactionType)}',
            ),
            Text(item.description),
            Text(
              persianDigits(
                'متراژ: ${item.area} · اتاق: ${item.bedrooms} · ساخت: ${item.yearBuilt}',
              ),
            ),
            ...listingPriceLabels(item).map(Text.new),
            ...item.specifications.entries
                .where((e) => e.value != null)
                .map(
                  (e) => ListTile(
                    title: Text(specificationLabels[e.key] ?? e.key),
                    subtitle: Text(specificationValue(e.key, e.value)),
                  ),
                ),
            const Divider(),
            Text(
              item.agencyName,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            FilledButton.icon(
              onPressed: item.agencyPhone.isEmpty
                  ? null
                  : () => launchPhoneCall(context, item.agencyPhone),
              icon: const Icon(Icons.call_outlined),
              label: Text(persianDigits('تماس با آژانس ${item.agencyPhone}')),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: () => context.push(
                '/conversations/new?listing=${item.id}&audience=${widget.audience}',
              ),
              icon: const Icon(Icons.chat_bubble_outline),
              label: const Text('شروع گفت‌وگو درباره این آگهی'),
            ),
          ],
        );
      },
    ),
  );
}

List<String> listingPriceLabels(Listing item) => [
  if (item.salePrice.isNotEmpty) persianDigits('قیمت: ${item.salePrice} تومان'),
  if (item.deposit.isNotEmpty) persianDigits('ودیعه: ${item.deposit} تومان'),
  if (item.rent.isNotEmpty) persianDigits('اجاره ماهانه: ${item.rent} تومان'),
];

const specificationLabels = <String, String>{
  'bathrooms': 'حمام',
  'floor_number': 'طبقه',
  'total_floors': 'تعداد طبقات',
  'parking_spaces': 'پارکینگ',
  'has_storage_room': 'انباری',
  'has_elevator': 'آسانسور',
  'has_balcony': 'بالکن',
  'units_per_floor': 'واحد در طبقه',
  'master_bedrooms': 'اتاق مستر',
  'toilet_types': 'سرویس بهداشتی',
  'cabinet_type': 'کابینت',
  'heating_type': 'گرمایش',
  'cooling_type': 'سرمایش',
  'flooring_type': 'کف‌پوش',
  'renovation_status': 'بازسازی',
  'building_orientation': 'جهت ساختمان',
  'deed_type': 'سند',
  'has_loan': 'وام',
  'is_exchangeable': 'معاوضه',
  'has_pool': 'استخر',
  'has_jacuzzi': 'جکوزی',
  'has_sauna': 'سونا',
  'building_type': 'نوع ساختمان',
  'structure_type': 'سازه',
  'has_water': 'آب',
  'has_electricity': 'برق',
  'has_gas': 'گاز',
  'land_area': 'مساحت زمین',
  'building_area': 'مساحت بنا',
  'can_aggregate': 'تجمیع',
  'land_frontage': 'بر زمین',
  'is_convertible': 'قابل تبدیل',
  'minimum_deposit': 'حداقل ودیعه',
};

String specificationValue(String key, Object? value) {
  if (value is bool) return value ? 'دارد' : 'ندارد';
  final choices = <String, List<ChoiceItem>>{
    'toilet_types': toiletTypes,
    'cabinet_type': cabinetTypes,
    'heating_type': heatingTypes,
    'cooling_type': coolingTypes,
    'flooring_type': flooringTypes,
    'renovation_status': renovationStatuses,
    'building_orientation': buildingOrientations,
    'deed_type': deedTypes,
    'building_type': buildingTypes,
  };
  final options = choices[key];
  if (options != null) {
    if (value is List) {
      return value.map((v) => labelOf(options, '$v')).join('، ');
    }
    return labelOf(options, '$value');
  }
  return persianDigits('$value');
}
