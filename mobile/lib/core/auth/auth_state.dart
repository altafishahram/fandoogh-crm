enum AuthStatus {
  restoring,
  signedOut,
  signedIn,
  passwordChangeRequired,
  agencySuspended,
}

final class AuthState {
  const AuthState({
    required this.status,
    this.user,
    this.message,
    this.isBusy = false,
  });

  const AuthState.restoring() : this(status: AuthStatus.restoring);
  const AuthState.signedOut({String? message})
    : this(status: AuthStatus.signedOut, message: message);

  final AuthStatus status;
  final Map<String, dynamic>? user;
  final String? message;
  final bool isBusy;

  bool get isAuthenticated =>
      status == AuthStatus.signedIn ||
      status == AuthStatus.passwordChangeRequired ||
      status == AuthStatus.agencySuspended;

  String get displayName => user?['name'] as String? ?? 'کارشناس';

  AuthState copyWith({
    AuthStatus? status,
    Map<String, dynamic>? user,
    String? message,
    bool clearMessage = false,
    bool? isBusy,
  }) => AuthState(
    status: status ?? this.status,
    user: user ?? this.user,
    message: clearMessage ? null : message ?? this.message,
    isBusy: isBusy ?? this.isBusy,
  );
}
