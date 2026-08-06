import 'dart:developer' as developer;

abstract final class AppLogger {
  static void error(String message, Object error, StackTrace? stackTrace) {
    developer.log(
      message,
      name: 'fandoogh.mobile',
      error: error,
      stackTrace: stackTrace,
      level: 1000,
    );
  }
}
