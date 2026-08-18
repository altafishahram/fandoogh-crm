import 'package:fandoogh_crm/core/config/app_config.dart';
import 'package:fandoogh_crm/core/localization/persian_date.dart';
import 'package:fandoogh_crm/features/properties/data/property_repository.dart';
import 'package:flutter/material.dart';

final class PropertyCard extends StatelessWidget {
  const PropertyCard({
    required this.title,
    required this.transactionType,
    required this.area,
    required this.salePrice,
    required this.depositAmount,
    required this.monthlyRent,
    required this.parkingSpaces,
    required this.ownerName,
    this.imageUrl,
    this.imageHeaders,
    this.pendingSync = false,
    this.onTap,
    super.key,
  });

  factory PropertyCard.fromRecord(
    PropertyRecord property, {
    Map<String, String>? imageHeaders,
    VoidCallback? onTap,
  }) {
    return PropertyCard(
      title: property.title,
      transactionType: property.transactionType,
      area:
          property.data['area_sqm'] ??
          property.data['land_area'] ??
          property.data['building_area'],
      salePrice: property.data['sale_price'],
      depositAmount: property.data['deposit_amount'],
      monthlyRent: property.data['monthly_rent'],
      parkingSpaces: property.data['parking_spaces'],
      ownerName: _ownerName(property.owners),
      imageUrl: _imageUrl(property.data),
      imageHeaders: imageHeaders,
      pendingSync: property.pendingSync,
      onTap: onTap,
    );
  }

  factory PropertyCard.fromMap(
    Map<String, dynamic> property, {
    Map<String, String>? imageHeaders,
    VoidCallback? onTap,
  }) {
    final data = _normalizedProperty(property);
    final rawOwners = data['owners'];
    final owners = rawOwners is List
        ? rawOwners
              .whereType<Map>()
              .map((owner) => Map<String, dynamic>.from(owner))
              .toList(growable: false)
        : const <Map<String, dynamic>>[];
    return PropertyCard(
      title: _text(data['title'] ?? data['name'], 'بدون عنوان'),
      transactionType: _text(
        data['transaction_type'] ?? data['transactionType'],
        'sale',
      ),
      area:
          data['area_sqm'] ??
          data['area'] ??
          data['land_area'] ??
          data['building_area'],
      salePrice: data['sale_price'] ?? data['salePrice'],
      depositAmount: data['deposit_amount'] ?? data['depositAmount'],
      monthlyRent: data['monthly_rent'] ?? data['monthlyRent'],
      parkingSpaces: data['parking_spaces'] ?? data['parkingSpaces'],
      ownerName: _text(data['owner_name'], _ownerName(owners)),
      imageUrl: _imageUrl(data),
      imageHeaders: imageHeaders,
      pendingSync: data['pending_sync'] == true,
      onTap: onTap,
    );
  }

  final String title;
  final String transactionType;
  final Object? area;
  final Object? salePrice;
  final Object? depositAmount;
  final Object? monthlyRent;
  final Object? parkingSpaces;
  final String ownerName;
  final String? imageUrl;
  final Map<String, String>? imageHeaders;
  final bool pendingSync;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final isRent = transactionType == 'rent';
    final theme = Theme.of(context);
    final textColor = theme.colorScheme.onSurface;

    return Card(
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: BorderSide(
          color: theme.colorScheme.outlineVariant.withValues(alpha: .7),
        ),
      ),
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Directionality(
            textDirection: TextDirection.rtl,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: <Widget>[
                Expanded(
                  child: Padding(
                    padding: const EdgeInsetsDirectional.only(end: 2),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: textColor,
                            fontSize: 16,
                            fontWeight: FontWeight.w900,
                            height: 1.3,
                          ),
                        ),
                        const SizedBox(height: 15),
                        _line(
                          context,
                          'متراژ',
                          _formatArea(area),
                          Icons.square_foot_rounded,
                        ),
                        if (isRent) ...<Widget>[
                          _line(
                            context,
                            'ودیعه',
                            formatToman(depositAmount),
                            Icons.payments_outlined,
                          ),
                          _line(
                            context,
                            'اجاره',
                            formatToman(monthlyRent),
                            Icons.payments_outlined,
                          ),
                        ] else
                          _amountLine(
                            context,
                            'مبلغ کل',
                            formatToman(salePrice),
                            Icons.payments_outlined,
                          ),
                        _line(
                          context,
                          'پارکینگ',
                          _hasParking(parkingSpaces) ? 'دارد' : 'ندارد',
                          Icons.directions_car_filled_outlined,
                        ),
                        _line(
                          context,
                          'مالک',
                          ownerName.trim().isEmpty ? 'بدون مالک' : ownerName,
                          Icons.person_outline_rounded,
                          divider: false,
                        ),
                        if (pendingSync)
                          Padding(
                            padding: const EdgeInsets.only(top: 3),
                            child: Text(
                              'در صف همگام‌سازی',
                              style: TextStyle(
                                color: theme.colorScheme.primary,
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                _PropertyCardImage(
                  imageUrl: imageUrl,
                  imageHeaders: imageHeaders,
                  isRent: isRent,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _amountLine(
    BuildContext context,
    String label,
    String value,
    IconData icon,
  ) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(
            bottom: BorderSide(
              color: theme.colorScheme.outlineVariant.withValues(alpha: .65),
            ),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.only(bottom: 5),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Icon(icon, size: 16, color: theme.colorScheme.primary),
              const SizedBox(width: 5),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: theme.colorScheme.onSurface,
                        fontFamily: theme.textTheme.bodyMedium?.fontFamily,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                        height: 1.2,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      value,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: theme.colorScheme.onSurface,
                        fontFamily: theme.textTheme.bodyMedium?.fontFamily,
                        fontSize: 13,
                        height: 1.2,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _line(
    BuildContext context,
    String label,
    String value,
    IconData icon, {
    bool divider = true,
  }) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: divider
              ? Border(
                  bottom: BorderSide(
                    color: theme.colorScheme.outlineVariant.withValues(
                      alpha: .65,
                    ),
                  ),
                )
              : null,
        ),
        child: Padding(
          padding: const EdgeInsets.only(bottom: 5),
          child: Row(
            children: <Widget>[
              Icon(icon, size: 16, color: theme.colorScheme.primary),
              const SizedBox(width: 5),
              Expanded(
                child: RichText(
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  text: TextSpan(
                    style: TextStyle(
                      color: theme.colorScheme.onSurface,
                      fontFamily: theme.textTheme.bodyMedium?.fontFamily,
                      fontSize: 13,
                      height: 1.3,
                    ),
                    children: <InlineSpan>[
                      TextSpan(
                        text: '$label: ',
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                      TextSpan(text: value),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  static String? _imageUrl(Map<String, dynamic> data) {
    for (final key in const <String>[
      'cover_image_url',
      'image_url',
      'first_image_url',
    ]) {
      final value = data[key];
      if (value is String && value.trim().isNotEmpty) return value;
    }

    for (final key in const <String>['cover_image', 'cover_image_url']) {
      final value = data[key];
      if (value is Map) {
        final url = _imageUrlFromMap(Map<String, dynamic>.from(value));
        if (url != null) return url;
      }
    }

    final rawImages = data['images'];
    if (rawImages is List) {
      final images = rawImages
          .whereType<Map>()
          .map(Map<String, dynamic>.from)
          .toList(growable: false);
      final cover = images.where((image) => image['is_cover'] == true);
      for (final image in <Map<String, dynamic>>[...cover, ...images]) {
        final url = _imageUrlFromMap(image);
        if (url != null) return url;
      }
    }
    return null;
  }

  static String? _imageUrlFromMap(Map<String, dynamic> image) {
    for (final key in const <String>[
      'content_url',
      'url',
      'image_url',
      'path',
    ]) {
      final value = image[key];
      if (value is String && value.trim().isNotEmpty) return value;
    }
    return null;
  }

  static Map<String, dynamic> _normalizedProperty(
    Map<String, dynamic> property,
  ) {
    var normalized = Map<String, dynamic>.from(property);
    for (final key in const <String>['data', 'attributes', 'property']) {
      final nested = normalized[key];
      if (nested is Map) {
        normalized = <String, dynamic>{
          ...normalized,
          ...Map<String, dynamic>.from(nested),
        };
      }
    }
    return normalized;
  }

  static String _text(Object? value, String fallback) {
    final text = '$value'.trim();
    return value == null || text.isEmpty || text == 'null' ? fallback : text;
  }

  static String _ownerName(List<Map<String, dynamic>> owners) {
    if (owners.isEmpty) return 'بدون مالک';
    return '${owners.first['full_name'] ?? owners.first['display_name'] ?? 'بدون مالک'}';
  }

  static bool _hasParking(Object? value) {
    if (value is bool) return value;
    final number = num.tryParse(latinDigits('$value'));
    return number != null && number > 0;
  }

  static String _formatArea(Object? value) {
    if (value == null || '$value'.trim().isEmpty) return '—';
    final number = num.tryParse(latinDigits('$value'));
    if (number == null) return '$value متر';
    final display = number % 1 == 0 ? number.toInt().toString() : '$number';
    return '${persianDigits(display)} متر';
  }
}

final class _PropertyCardImage extends StatelessWidget {
  const _PropertyCardImage({
    required this.imageUrl,
    required this.imageHeaders,
    required this.isRent,
  });

  final String? imageUrl;
  final Map<String, String>? imageHeaders;
  final bool isRent;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 144,
      height: 144,
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          ClipRRect(borderRadius: BorderRadius.circular(16), child: _image()),
          Positioned(
            top: 8,
            left: 8,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: isRent
                    ? const Color(0xFF6F9A73)
                    : const Color(0xFFC94A4A),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                child: Text(
                  isRent ? 'اجاره' : 'فروش',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _image() {
    final value = imageUrl?.trim();
    if (value == null || value.isEmpty) {
      return const _PropertyImagePlaceholder();
    }
    return Image.network(
      _contentUrl(value),
      fit: BoxFit.cover,
      headers: imageHeaders,
      errorBuilder: (_, _, _) => const _PropertyImagePlaceholder(),
    );
  }

  static String _contentUrl(String value) {
    final uri = Uri.tryParse(value);
    final base = Uri.parse(AppConfig.apiBaseUrl);
    if (uri == null) return value;
    if (!uri.hasScheme || uri.host.isEmpty) {
      return base.resolve(value.startsWith('/') ? value : '/$value').toString();
    }
    if ((uri.host == base.host ||
            uri.host == 'localhost' ||
            uri.host == '127.0.0.1') &&
        uri.scheme == 'http' &&
        base.scheme == 'https') {
      return uri
          .replace(
            scheme: base.scheme,
            host: base.host,
            port: base.hasPort ? base.port : null,
          )
          .toString();
    }
    return value;
  }
}

final class _PropertyImagePlaceholder extends StatelessWidget {
  const _PropertyImagePlaceholder();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            colors.primary.withValues(alpha: .16),
            colors.primary.withValues(alpha: .48),
          ],
        ),
      ),
      child: Center(
        child: Icon(
          Icons.apartment_rounded,
          size: 42,
          color: colors.onPrimaryContainer.withValues(alpha: .72),
        ),
      ),
    );
  }
}
