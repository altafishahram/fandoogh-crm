final class PagedResult<T> {
  const PagedResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  final List<T> items;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  PagedResult<T> append(PagedResult<T> next) => PagedResult<T>(
    items: <T>[...items, ...next.items],
    currentPage: next.currentPage,
    lastPage: next.lastPage,
  );

  static PagedResult<T> fromEnvelope<T>(
    Map<String, dynamic> envelope,
    T Function(Map<String, dynamic>) decode,
  ) {
    final rawItems = envelope['data'];
    final meta = envelope['meta'];
    final items = rawItems is List
        ? rawItems
              .whereType<Map>()
              .map((item) => decode(Map<String, dynamic>.from(item)))
              .toList(growable: false)
        : <T>[];
    final metadata = meta is Map
        ? Map<String, dynamic>.from(meta)
        : <String, dynamic>{};
    return PagedResult<T>(
      items: items,
      currentPage: (metadata['current_page'] as num?)?.toInt() ?? 1,
      lastPage: (metadata['last_page'] as num?)?.toInt() ?? 1,
    );
  }
}
