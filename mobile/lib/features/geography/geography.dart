import 'dart:convert';

import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final geographyRepositoryProvider = Provider<GeographyRepository>(
  (ref) => GeographyRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStoreProvider),
  ),
);

final class RegionSelection {
  const RegionSelection({
    this.provinceId,
    this.countyId,
    this.cityId,
    this.cityName,
  });
  final int? provinceId;
  final int? countyId;
  final int? cityId;
  final String? cityName;
  factory RegionSelection.fromJson(
    Map<String, dynamic> data, {
    String prefix = '',
  }) => RegionSelection(
    provinceId: (data['${prefix}province_id'] as num?)?.toInt(),
    countyId: (data['${prefix}county_id'] as num?)?.toInt(),
    cityId: (data['${prefix}city_id'] as num?)?.toInt(),
    cityName: data[prefix.isEmpty ? 'city' : '${prefix}city'] as String?,
  );
  Map<String, Object?> toJson({String prefix = ''}) => {
    '${prefix}province_id': provinceId,
    '${prefix}county_id': countyId,
    '${prefix}city_id': cityId,
  };
}

RegionSelection agencyRegion(WidgetRef ref) {
  if (!ref.exists(authControllerProvider)) return const RegionSelection();
  final agency = ref.read(authControllerProvider).user?['agency'];
  return agency is Map
      ? RegionSelection.fromJson(Map<String, dynamic>.from(agency))
      : const RegionSelection();
}

final class GeographyRepository extends ApiRepository {
  const GeographyRepository(super.client, this.tokens);
  final TokenStore tokens;
  Future<List<Map<String, dynamic>>> choices(
    String level, {
    int? parent,
  }) async {
    final cacheKey = 'geo_reference_${level}_${parent ?? 0}';
    final storage = tokens;
    try {
      final values = await getList(
        '/locations/$level',
        query: {
          if (level == 'counties') 'province_id': parent,
          if (level == 'cities') 'county_id': parent,
        },
      );
      // Reference data has no tenant or private content; it is safe to cache separately.
      if (storage is SecureTokenStore) {
        await storage.writeReference(cacheKey, jsonEncode(values));
      }
      return values;
    } catch (error) {
      final failure = apiFailureFrom(error);
      if (failure.code != 'NETWORK_UNAVAILABLE' &&
          failure.code != 'NETWORK_TIMEOUT') {
        rethrow;
      }
      final cached = storage is SecureTokenStore
          ? await storage.readReference(cacheKey)
          : null;
      if (cached == null) rethrow;
      return (jsonDecode(cached) as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    }
  }
}

/// Parent changes clear every dependent identifier, even before a request finishes.
final class RegionFields extends ConsumerStatefulWidget {
  const RegionFields({
    required this.value,
    required this.onChanged,
    this.requiredCity = false,
    this.legacyCity,
    super.key,
  });
  final RegionSelection value;
  final ValueChanged<RegionSelection> onChanged;
  final bool requiredCity;
  final String? legacyCity;
  @override
  ConsumerState<RegionFields> createState() => _RegionFieldsState();
}

final class _RegionFieldsState extends ConsumerState<RegionFields> {
  List<Map<String, dynamic>> _provinces = [], _counties = [], _cities = [];
  Object? _error;
  bool _loading = false;
  int _revision = 0;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(RegionFields oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value.provinceId != widget.value.provinceId ||
        oldWidget.value.countyId != widget.value.countyId) {
      _load();
    }
  }

  Future<void> _load() async {
    final revision = ++_revision;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repository = ref.read(geographyRepositoryProvider);
      final provinces = await repository.choices('provinces');
      final counties = widget.value.provinceId == null
          ? <Map<String, dynamic>>[]
          : await repository.choices(
              'counties',
              parent: widget.value.provinceId,
            );
      final cities = widget.value.countyId == null
          ? <Map<String, dynamic>>[]
          : await repository.choices('cities', parent: widget.value.countyId);
      if (!mounted || revision != _revision) return;
      setState(() {
        _provinces = provinces;
        _counties = counties;
        _cities = cities;
        _loading = false;
      });
    } catch (error) {
      if (!mounted || revision != _revision) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Widget _choice(
    String label,
    int? value,
    List<Map<String, dynamic>> items,
    ValueChanged<int?> change, {
    bool required = false,
  }) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: DropdownButtonFormField<int>(
      key: ValueKey('$label-$value-${items.length}'),
      initialValue: items.any((item) => item['id'] == value) ? value : null,
      isExpanded: true,
      decoration: InputDecoration(labelText: label),
      items: items
          .map(
            (item) => DropdownMenuItem<int>(
              value: (item['id'] as num).toInt(),
              child: Text('${item['name']}', overflow: TextOverflow.ellipsis),
            ),
          )
          .toList(),
      onChanged: _loading || items.isEmpty ? null : change,
      validator: required && widget.legacyCity?.isNotEmpty != true
          ? (_) => widget.value.cityId == null ? 'شهر را انتخاب کنید.' : null
          : null,
    ),
  );
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      if (widget.legacyCity?.isNotEmpty == true && widget.value.cityId == null)
        Text(
          'شهر ثبت‌شده: ${widget.legacyCity}؛ برای اصلاح، منطقه را انتخاب کنید.',
        ),
      _choice(
        'استان',
        widget.value.provinceId,
        _provinces,
        (id) => widget.onChanged(RegionSelection(provinceId: id)),
      ),
      _choice(
        'شهرستان',
        widget.value.countyId,
        _counties,
        (id) => widget.onChanged(
          RegionSelection(provinceId: widget.value.provinceId, countyId: id),
        ),
      ),
      _choice(
        'شهر',
        widget.value.cityId,
        _cities,
        (id) => widget.onChanged(
          RegionSelection(
            provinceId: widget.value.provinceId,
            countyId: widget.value.countyId,
            cityId: id,
            cityName:
                _cities.where((e) => e['id'] == id).firstOrNull?['name']
                    as String?,
          ),
        ),
        required: widget.requiredCity,
      ),
      if (_loading)
        const LinearProgressIndicator(semanticsLabel: 'دریافت مناطق'),
      if (_error != null)
        TextButton.icon(
          onPressed: _load,
          icon: const Icon(Icons.refresh),
          label: Text('${apiFailureFrom(_error!).message} — تلاش دوباره'),
        ),
    ],
  );
}
