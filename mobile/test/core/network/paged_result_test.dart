import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('decodes Laravel pagination and appends the next page', () {
    final first = PagedResult.fromEnvelope<int>(<String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{'id': 1},
        <String, dynamic>{'id': 2},
      ],
      'meta': <String, dynamic>{'current_page': 1, 'last_page': 2},
    }, (item) => item['id'] as int);
    final second = PagedResult.fromEnvelope<int>(<String, dynamic>{
      'data': <Map<String, dynamic>>[
        <String, dynamic>{'id': 3},
      ],
      'meta': <String, dynamic>{'current_page': 2, 'last_page': 2},
    }, (item) => item['id'] as int);

    expect(first.hasMore, isTrue);
    expect(first.append(second).items, <int>[1, 2, 3]);
    expect(first.append(second).hasMore, isFalse);
  });
}
