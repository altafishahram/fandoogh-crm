import 'dart:ui';

import 'package:fandoogh_crm/app/app.dart';
import 'package:fandoogh_crm/core/logging/app_logger.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

void main() {
  FlutterError.onError = (details) {
    AppLogger.error(
      'Unhandled Flutter framework error',
      details.exception,
      details.stack,
    );
  };

  PlatformDispatcher.instance.onError = (error, stackTrace) {
    AppLogger.error('Unhandled platform error', error, stackTrace);
    return true;
  };

  runApp(const ProviderScope(child: FandooghApp()));
}
