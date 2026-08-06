import 'package:flutter/services.dart';

abstract final class AndroidMediaPicker {
  static const _channel = MethodChannel('com.fandoogh.crm/media');

  static Future<String?> pickImage() =>
      _channel.invokeMethod<String>('pickImage');
}
