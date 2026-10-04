import 'package:fandoogh_crm/core/localization/persian_date.dart';

typedef MatchNotificationJson = Map<String, dynamic>;

/// مدل خواندنی اعلان تطبیق.
///
/// پاسخ‌های نسخه‌های مختلف API ممکن است اطلاعات تطبیق را مستقیماً در اعلان
/// یا داخل کلیدهای `match` و `property_customer_match` برگردانند؛ مدل هر دو
/// شکل را پشتیبانی می‌کند تا cache آفلاین هم پایدار بماند.
final class MatchNotification {
  const MatchNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.isRead,
    this.matchId,
    this.propertyId,
    this.customerId,
    this.score,
    this.matchMode,
    this.shortReason = '',
    this.propertyRank,
    this.customerRank,
    this.createdAt,
    this.updatedAt,
    this.readAt,
    this.version,
    this.matchedDepositMin,
    this.matchedDepositMax,
    this.matchedRentMin,
    this.matchedRentMax,
    this.property,
    this.customer,
  });

  final int id;
  final String title;
  final String body;
  final bool isRead;
  final int? matchId;
  final int? propertyId;
  final int? customerId;
  final double? score;
  final String? matchMode;
  final String shortReason;
  final int? propertyRank;
  final int? customerRank;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? readAt;
  final int? version;
  final double? matchedDepositMin;
  final double? matchedDepositMax;
  final double? matchedRentMin;
  final double? matchedRentMax;
  final MatchNotificationJson? property;
  final MatchNotificationJson? customer;

  factory MatchNotification.fromJson(MatchNotificationJson json) {
    final match = _asMap(
      json['match'] ??
          json['property_customer_match'] ??
          json['propertyCustomerMatch'],
    );
    final property = _asMap(json['property'] ?? match?['property']);
    final customer = _asMap(json['customer'] ?? match?['customer']);
    final financial = _asMap(match?['financial_range']);
    final readAt = _asDateTime(
      json['read_at'] ?? json['readAt'] ?? json['readAtUtc'],
    );

    return MatchNotification(
      id: _asInt(json['id'] ?? json['notification_id']) ?? 0,
      title: _asText(json['title']) ?? 'تطبیق جدید',
      body: _asText(json['body'] ?? json['message']) ?? '',
      isRead:
          json.containsKey('is_read') ||
              json.containsKey('isRead') ||
              json.containsKey('read')
          ? _asBool(json['is_read'] ?? json['isRead'] ?? json['read'])
          : readAt != null,
      matchId: _asInt(
        json['match_id'] ?? json['property_customer_match_id'] ?? match?['id'],
      ),
      propertyId: _asInt(
        json['property_id'] ?? match?['property_id'] ?? property?['id'],
      ),
      customerId: _asInt(
        json['customer_id'] ?? match?['customer_id'] ?? customer?['id'],
      ),
      score: _asDouble(json['score'] ?? match?['score']),
      matchMode: _asText(json['match_mode'] ?? match?['match_mode']),
      shortReason:
          _asText(json['short_reason'] ?? match?['short_reason']) ?? '',
      propertyRank: _asInt(json['property_rank'] ?? match?['property_rank']),
      customerRank: _asInt(json['customer_rank'] ?? match?['customer_rank']),
      createdAt: _asDateTime(json['created_at'] ?? json['createdAt']),
      updatedAt: _asDateTime(json['updated_at'] ?? json['updatedAt']),
      readAt: readAt,
      version: _asInt(json['version'] ?? json['notification_version']),
      matchedDepositMin: _asDouble(
        json['matched_deposit_min'] ??
            match?['matched_deposit_min'] ??
            financial?['deposit_min'],
      ),
      matchedDepositMax: _asDouble(
        json['matched_deposit_max'] ??
            match?['matched_deposit_max'] ??
            financial?['deposit_max'],
      ),
      matchedRentMin: _asDouble(
        json['matched_rent_min'] ??
            match?['matched_rent_min'] ??
            financial?['rent_min'],
      ),
      matchedRentMax: _asDouble(
        json['matched_rent_max'] ??
            match?['matched_rent_max'] ??
            financial?['rent_max'],
      ),
      property: property,
      customer: customer,
    );
  }

  String get propertyTitle {
    final value = _asText(property?['title'] ?? property?['name']);
    return value == null || value.isEmpty ? 'ملک مرتبط' : value;
  }

  String get customerName {
    final fullName = _asText(
      customer?['full_name'] ?? customer?['fullName'] ?? customer?['name'],
    );
    if (fullName != null && fullName.isNotEmpty) return fullName;
    final firstName = _asText(
      customer?['first_name'] ?? customer?['firstName'],
    );
    final lastName = _asText(customer?['last_name'] ?? customer?['lastName']);
    final value = [
      firstName,
      lastName,
    ].whereType<String>().where((part) => part.isNotEmpty).join(' ').trim();
    return value.isEmpty ? 'مشتری مرتبط' : value;
  }

  String get modeLabel => switch (matchMode) {
    'converted' || 'conversion' => 'تطبیق با تبدیل ودیعه و اجاره',
    'direct' => 'تطبیق مستقیم',
    _ => 'تطبیق',
  };

  String get scoreLabel {
    final value = score;
    if (value == null) return 'امتیاز ثبت نشده';
    final formatted = value % 1 == 0
        ? value.toInt().toString()
        : value.toStringAsFixed(1);
    return '${persianDigits(formatted)} از ۱۰۰';
  }

  String get dateLabel => PersianDate.format(createdAt);

  MatchNotification copyWith({
    bool? isRead,
    DateTime? readAt,
    DateTime? updatedAt,
  }) {
    return MatchNotification(
      id: id,
      title: title,
      body: body,
      isRead: isRead ?? this.isRead,
      matchId: matchId,
      propertyId: propertyId,
      customerId: customerId,
      score: score,
      matchMode: matchMode,
      shortReason: shortReason,
      propertyRank: propertyRank,
      customerRank: customerRank,
      createdAt: createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      readAt: readAt ?? this.readAt,
      version: version,
      matchedDepositMin: matchedDepositMin,
      matchedDepositMax: matchedDepositMax,
      matchedRentMin: matchedRentMin,
      matchedRentMax: matchedRentMax,
      property: property,
      customer: customer,
    );
  }

  MatchNotificationJson toJson() => <String, dynamic>{
    'id': id,
    'title': title,
    'body': body,
    'is_read': isRead,
    'read_at': readAt?.toUtc().toIso8601String(),
    'match_id': matchId,
    'property_id': propertyId,
    'customer_id': customerId,
    'score': score,
    'match_mode': matchMode,
    'short_reason': shortReason,
    'property_rank': propertyRank,
    'customer_rank': customerRank,
    'created_at': createdAt?.toUtc().toIso8601String(),
    'updated_at': (updatedAt ?? createdAt)?.toUtc().toIso8601String(),
    'version': version,
    'matched_deposit_min': matchedDepositMin,
    'matched_deposit_max': matchedDepositMax,
    'matched_rent_min': matchedRentMin,
    'matched_rent_max': matchedRentMax,
    if (property != null) 'property': property,
    if (customer != null) 'customer': customer,
  };

  static MatchNotificationJson? _asMap(Object? value) {
    if (value is Map) return MatchNotificationJson.from(value);
    return null;
  }

  static String? _asText(Object? value) {
    if (value == null) return null;
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? null : text;
  }

  static int? _asInt(Object? value) {
    if (value is num) return value.toInt();
    return int.tryParse('$value');
  }

  static double? _asDouble(Object? value) {
    if (value is num) return value.toDouble();
    return double.tryParse('$value');
  }

  static bool _asBool(Object? value) {
    if (value is bool) return value;
    return value == 1 || '$value'.toLowerCase() == 'true';
  }

  static DateTime? _asDateTime(Object? value) {
    if (value is DateTime) return value;
    return value == null ? null : DateTime.tryParse('$value');
  }
}

final class MatchNotificationPage {
  const MatchNotificationPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    this.unreadCount,
    this.fromCache = false,
    this.total,
    this.perPage,
  });

  final List<MatchNotification> items;
  final int currentPage;
  final int lastPage;
  final int? unreadCount;
  final bool fromCache;
  final int? total;
  final int? perPage;

  bool get hasMore => currentPage < lastPage;

  factory MatchNotificationPage.fromEnvelope(
    MatchNotificationJson envelope, {
    bool fromCache = false,
  }) {
    final rawData = envelope['data'];
    final dataMap = rawData is Map ? MatchNotificationJson.from(rawData) : null;
    final rawItems = rawData is List
        ? rawData
        : dataMap?['items'] is List
        ? dataMap!['items']
        : dataMap?['notifications'] is List
        ? dataMap!['notifications']
        : const <dynamic>[];
    final items = rawItems is List
        ? rawItems
              .whereType<Map>()
              .map(
                (item) => MatchNotification.fromJson(
                  MatchNotificationJson.from(item),
                ),
              )
              .toList(growable: false)
        : const <MatchNotification>[];
    final rawMeta = envelope['meta'] ?? dataMap?['meta'];
    final meta = rawMeta is Map
        ? MatchNotificationJson.from(rawMeta)
        : const <String, dynamic>{};
    final currentPage =
        _intFrom(meta['current_page'] ?? dataMap?['current_page']) ?? 1;
    final lastPage =
        _intFrom(meta['last_page'] ?? dataMap?['last_page']) ?? currentPage;
    final unreadCount = _intFrom(
      meta['unread_count'] ??
          dataMap?['unread_count'] ??
          envelope['unread_count'],
    );
    return MatchNotificationPage(
      items: items,
      currentPage: currentPage,
      lastPage: lastPage,
      unreadCount: unreadCount,
      fromCache: fromCache,
      total: _intFrom(meta['total'] ?? dataMap?['total']),
      perPage: _intFrom(meta['per_page'] ?? dataMap?['per_page']),
    );
  }

  MatchNotificationPage copyWith({
    List<MatchNotification>? items,
    int? currentPage,
    int? lastPage,
    int? unreadCount,
    bool? fromCache,
    int? total,
    int? perPage,
  }) => MatchNotificationPage(
    items: items ?? this.items,
    currentPage: currentPage ?? this.currentPage,
    lastPage: lastPage ?? this.lastPage,
    unreadCount: unreadCount ?? this.unreadCount,
    fromCache: fromCache ?? this.fromCache,
    total: total ?? this.total,
    perPage: perPage ?? this.perPage,
  );

  static int? _intFrom(Object? value) {
    if (value is num) return value.toInt();
    return int.tryParse('$value');
  }
}
