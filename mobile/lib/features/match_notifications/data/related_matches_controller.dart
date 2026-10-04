import 'package:fandoogh_crm/features/match_notifications/data/match_notification.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_notification_repository.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_refresh.dart';
import 'package:fandoogh_crm/features/match_notifications/data/match_summary.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final relatedMatchesProvider = AsyncNotifierProvider.autoDispose
    .family<RelatedMatchesController, MatchNotificationPage, RelatedMatchScope>(
      RelatedMatchesController.new,
    );

final class RelatedMatchesController
    extends AsyncNotifier<MatchNotificationPage> {
  RelatedMatchesController(this.scope);
  final RelatedMatchScope scope;
  bool _loadingMore = false;
  bool _expanded = false;
  String? _identity;
  int _generation = 0;

  @override
  Future<MatchNotificationPage> build() {
    ref.watch(matchSessionProvider);
    ref.watch(matchDataRevisionProvider);
    final identity = ref.watch(matchIdentityProvider);
    if (_identity != identity) _expanded = false;
    _identity = identity;
    _generation++;
    final repository = ref.watch(matchNotificationRepositoryProvider);
    return _reload(repository);
  }

  Future<void> refresh() async {
    _generation++;
    state = await AsyncValue.guard(
      () => _reload(ref.read(matchNotificationRepositoryProvider)),
    );
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null ||
        !current.hasMore ||
        current.items.length >= 20 ||
        current.currentPage >= 2 ||
        _loadingMore) {
      return;
    }
    _loadingMore = true;
    final generation = _generation;
    try {
      final next = await ref
          .read(matchNotificationRepositoryProvider)
          .related(scope, page: current.currentPage + 1);
      if (!ref.mounted || generation != _generation) return;
      if (next.fromCache && next.total == null) {
        throw const ApiFailure(
          code: 'RELATED_PAGE_NOT_CACHED',
          message:
              'این صفحه در حالت آفلاین ذخیره نشده است؛ پس از اتصال دوباره تلاش کنید.',
        );
      }
      _expanded = true;
      state = AsyncData(_append(current, next));
    } finally {
      _loadingMore = false;
    }
  }

  Future<MatchNotificationPage> _reload(
    MatchNotificationRepository repository,
  ) async {
    final first = await repository.related(scope);
    if (!_expanded || !first.hasMore) return first;
    final second = await repository.related(scope, page: 2);
    if (second.fromCache && second.total == null) return first;
    return _append(first, second);
  }

  static MatchNotificationPage _append(
    MatchNotificationPage first,
    MatchNotificationPage second,
  ) {
    final unique = <int, MatchNotification>{
      for (final item in first.items) item.id: item,
      for (final item in second.items) item.id: item,
    };
    return second.copyWith(
      items: unique.values.take(20).toList(growable: false),
      total: second.total ?? first.total,
      unreadCount: second.unreadCount ?? first.unreadCount,
    );
  }
}
