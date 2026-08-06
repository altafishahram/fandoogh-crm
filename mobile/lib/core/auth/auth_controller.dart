import 'package:fandoogh_crm/core/auth/auth_state.dart';
import 'package:fandoogh_crm/core/errors/api_failure.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/storage/token_store.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final authControllerProvider = NotifierProvider<AuthController, AuthState>(
  AuthController.new,
);

final class AuthController extends Notifier<AuthState> {
  late ApiClient _client;
  late TokenStore _tokens;

  @override
  AuthState build() {
    _client = ref.read(apiClientProvider);
    _tokens = ref.read(tokenStoreProvider);
    _client.onAccessFailure = _handleAccessFailure;
    ref.onDispose(() => _client.onAccessFailure = null);
    Future<void>.microtask(restoreSession);
    return const AuthState.restoring();
  }

  Future<void> restoreSession() async {
    try {
      final token = await _tokens.read();
      if (token == null || token.isEmpty) {
        state = const AuthState.signedOut();
        return;
      }
      _client.token = token;
      final response = await _client.dio.get<Map<String, dynamic>>('/auth/me');
      final user = _data(response.data);
      state = AuthState(
        status: user['must_change_password'] == true
            ? AuthStatus.passwordChangeRequired
            : AuthStatus.signedIn,
        user: user,
      );
    } catch (error) {
      await _clearCredentials();
      final failure = apiFailureFrom(error);
      state = AuthState.signedOut(
        message: failure.statusCode == 401
            ? 'نشست شما پایان یافته است؛ دوباره وارد شوید.'
            : failure.message,
      );
    }
  }

  Future<bool> login({required String email, required String password}) async {
    state = state.copyWith(isBusy: true, clearMessage: true);
    try {
      final response = await _client.dio.post<Map<String, dynamic>>(
        '/auth/login',
        data: <String, Object?>{
          'email': email.trim().toLowerCase(),
          'password': password,
          'device_name': 'ملک بان اندروید',
        },
      );
      final payload = _data(response.data);
      final token = payload['token'] as String;
      await _tokens.write(token);
      _client.token = token;

      if (payload['must_change_password'] == true) {
        state = const AuthState(status: AuthStatus.passwordChangeRequired);
      } else {
        final me = await _client.dio.get<Map<String, dynamic>>('/auth/me');
        state = AuthState(status: AuthStatus.signedIn, user: _data(me.data));
      }
      return true;
    } catch (error) {
      final failure = apiFailureFrom(error);
      state = AuthState.signedOut(message: _friendly(failure));
      return false;
    }
  }

  Future<bool> changePassword({
    required String currentPassword,
    required String password,
  }) async {
    state = state.copyWith(isBusy: true, clearMessage: true);
    try {
      final response = await _client.dio.put<Map<String, dynamic>>(
        '/profile/password',
        data: <String, Object?>{
          'current_password': currentPassword,
          'password': password,
          'password_confirmation': password,
          'device_name': 'ملک بان اندروید',
        },
      );
      final payload = _data(response.data);
      final token = payload['token'] as String;
      await _tokens.write(token);
      _client.token = token;
      final me = await _client.dio.get<Map<String, dynamic>>('/auth/me');
      state = AuthState(status: AuthStatus.signedIn, user: _data(me.data));
      return true;
    } catch (error) {
      state = state.copyWith(
        isBusy: false,
        message: _friendly(apiFailureFrom(error)),
      );
      return false;
    }
  }

  Future<bool> updateProfile({
    required String name,
    required String email,
    String? phone,
  }) async {
    state = state.copyWith(isBusy: true, clearMessage: true);
    try {
      final response = await _client.dio.patch<Map<String, dynamic>>(
        '/profile',
        data: <String, Object?>{
          'name': name.trim(),
          'email': email.trim().toLowerCase(),
          'phone': phone?.trim().isEmpty == true ? null : phone?.trim(),
        },
      );
      state = AuthState(
        status: AuthStatus.signedIn,
        user: _data(response.data),
      );
      return true;
    } catch (error) {
      state = state.copyWith(
        isBusy: false,
        message: _friendly(apiFailureFrom(error)),
      );
      return false;
    }
  }

  Future<void> logout({bool allDevices = false}) async {
    state = state.copyWith(isBusy: true);
    try {
      await _client.dio.post<void>(
        allDevices ? '/auth/logout-all' : '/auth/logout',
      );
    } catch (_) {
      // Local logout must remain available when the server is unreachable.
    } finally {
      await _clearCredentials();
      state = const AuthState.signedOut();
    }
  }

  void clearMessage() => state = state.copyWith(clearMessage: true);

  Future<void> _handleAccessFailure(ApiFailure failure) async {
    if (failure.code == 'AGENCY_INACTIVE') {
      state = state.copyWith(
        status: AuthStatus.agencySuspended,
        isBusy: false,
        message: 'دسترسی آژانس شما تعلیق شده است.',
      );
      return;
    }
    if (failure.code == 'PASSWORD_CHANGE_REQUIRED') {
      state = state.copyWith(
        status: AuthStatus.passwordChangeRequired,
        isBusy: false,
      );
      return;
    }
    if (failure.statusCode == 401) {
      await _clearCredentials();
      state = const AuthState.signedOut(
        message: 'نشست شما پایان یافته است؛ دوباره وارد شوید.',
      );
    }
  }

  Future<void> _clearCredentials() async {
    _client.token = null;
    await _tokens.clear();
  }

  static Map<String, dynamic> _data(Map<String, dynamic>? envelope) {
    final value = envelope?['data'];
    return value is Map<String, dynamic> ? value : <String, dynamic>{};
  }

  static String _friendly(ApiFailure failure) => switch (failure.code) {
    'INVALID_CREDENTIALS' => 'ایمیل یا رمز عبور درست نیست.',
    'RATE_LIMITED' => 'تلاش‌های ورود زیاد بود؛ کمی بعد دوباره امتحان کنید.',
    'VALIDATION_FAILED' => 'اطلاعات واردشده معتبر نیست.',
    'NETWORK_UNAVAILABLE' => 'ارتباط با سرور برقرار نشد.',
    'NETWORK_TIMEOUT' => 'پاسخ سرور بیش از حد طول کشید.',
    _ => failure.message,
  };
}
