import 'package:fandoogh_crm/core/auth/auth_controller.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Includes access state so no in-memory result survives a user/tenant/role
/// change. Connectivity transitions also re-fetch summaries and related lists.
final matchIdentityProvider = Provider<String>((ref) {
  return ref.watch(
    authControllerProvider.select((auth) {
      final permissions = auth.permissions.toList()..sort();
      final agency = auth.user?['agency'];
      return '${auth.user?['id']}:${agency is Map ? agency['id'] : null}:'
          '${auth.status.name}:${auth.role}:${permissions.join(',')}';
    }),
  );
});

final matchSessionProvider = Provider<String>(
  (ref) =>
      '${ref.watch(matchIdentityProvider)}:'
      '${ref.watch(authControllerProvider.select((auth) => auth.isOffline))}',
);

final matchDataRevisionProvider = NotifierProvider<MatchDataRevision, int>(
  MatchDataRevision.new,
);

final class MatchDataRevision extends Notifier<int> {
  @override
  int build() => 0;
  void refresh() => state++;
}
