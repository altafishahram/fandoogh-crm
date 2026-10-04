import 'dart:convert';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final secureStorageProvider = Provider<FlutterSecureStorage>(
  (_) => const FlutterSecureStorage(),
);

final tokenStoreProvider = Provider<TokenStore>(
  (ref) => SecureTokenStore(ref.watch(secureStorageProvider)),
);

abstract interface class TokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<Map<String, dynamic>?> readUser();
  Future<void> writeUser(Map<String, dynamic> user);
  Future<void> clear();
}

final class SecureTokenStore implements TokenStore {
  const SecureTokenStore(this._storage, {this.namespace = 'mobile'});

  final String namespace;
  String get _key => '${namespace}_access_token';
  String get _userKey => '${namespace}_cached_user';
  final FlutterSecureStorage _storage;

  Future<String?> readReference(String key) => _storage.read(key: key);
  Future<void> writeReference(String key, String value) =>
      _storage.write(key: key, value: value);

  @override
  Future<String?> read() => _storage.read(key: _key);

  @override
  Future<void> write(String token) => _storage.write(key: _key, value: token);

  @override
  Future<Map<String, dynamic>?> readUser() async {
    final value = await _storage.read(key: _userKey);
    if (value == null) return null;
    final decoded = jsonDecode(value);
    return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
  }

  @override
  Future<void> writeUser(Map<String, dynamic> user) =>
      _storage.write(key: _userKey, value: jsonEncode(user));

  @override
  Future<void> clear() async {
    await _storage.delete(key: _key);
    await _storage.delete(key: _userKey);
  }
}
