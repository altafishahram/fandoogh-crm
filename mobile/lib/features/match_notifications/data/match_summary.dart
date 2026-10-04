/// A missing summary is unknown, not an empty match set.
final class MatchSummary {
  const MatchSummary({required this.count, required this.unreadCount});

  final int count;
  final int unreadCount;

  static MatchSummary? fromJson(Object? value) {
    if (value is! Map) return null;
    final count = int.tryParse('${value['count']}');
    final unread = int.tryParse('${value['unread_count']}');
    if (count == null || unread == null || count < 0 || unread < 0) return null;
    return MatchSummary(count: count, unreadCount: unread.clamp(0, count));
  }

  MatchSummary readOne() => MatchSummary(
    count: count,
    unreadCount: (unreadCount - 1).clamp(0, count),
  );

  Map<String, dynamic> toJson() => <String, dynamic>{
    'count': count,
    'unread_count': unreadCount,
  };
}

enum RelatedMatchSide { property, customer }

final class RelatedMatchScope {
  const RelatedMatchScope(this.side, this.id);
  const RelatedMatchScope.property(this.id) : side = RelatedMatchSide.property;
  const RelatedMatchScope.customer(this.id) : side = RelatedMatchSide.customer;

  final RelatedMatchSide side;
  final int id;
  String get resource =>
      side == RelatedMatchSide.property ? 'properties' : 'customers';
  String get path => '/$resource/$id/matches';
  String get cacheKey => '${side.name}:$id';

  @override
  bool operator ==(Object other) =>
      other is RelatedMatchScope && other.side == side && other.id == id;

  @override
  int get hashCode => Object.hash(side, id);
}
