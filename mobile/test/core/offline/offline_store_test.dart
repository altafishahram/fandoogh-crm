import 'package:fandoogh_crm/core/offline/offline_store.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() => FlutterSecureStorage.setMockInitialValues(<String, String>{}));

  test(
    'keeps cached records and queued operations inside user scope',
    () async {
      const storage = FlutterSecureStorage();
      final store = OfflineStore(storage)
        ..configure(<String, dynamic>{
          'id': 7,
          'agency': <String, dynamic>{'id': 3},
        });

      await store.mergeRecords('properties', <Map<String, dynamic>>[
        <String, dynamic>{'id': 11, 'title': 'ملک آزمایشی'},
      ]);
      final operation = await store.enqueue(
        resource: 'properties',
        action: 'create',
        path: '/properties',
        body: <String, Object?>{'title': 'ملک آفلاین'},
        operationKey: '018f47a0-8295-7000-8000-000000000001',
      );

      expect(
        (await store.records('properties')).single['title'],
        'ملک آزمایشی',
      );
      expect(
        operation['operation_key'],
        '018f47a0-8295-7000-8000-000000000001',
      );
      expect(await store.pendingCount(), 1);

      await store.clearScope();
      expect(store.isConfigured, isFalse);
    },
  );
}
