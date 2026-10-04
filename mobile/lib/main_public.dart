import 'package:fandoogh_crm/app/public_app.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

void main() => runApp(
  ProviderScope(
    overrides: [
      tokenStoreProvider.overrideWith(
        (ref) => SecureTokenStore(
          ref.watch(secureStorageProvider),
          namespace: 'public',
        ),
      ),
    ],
    child: const PublicApp(),
  ),
);
