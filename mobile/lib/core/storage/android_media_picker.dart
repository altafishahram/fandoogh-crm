import 'package:flutter/services.dart';

abstract final class AndroidMediaPicker {
  static const _channel = MethodChannel('com.fandoogh.crm/media');

  static Future<String?> pickImage() =>
      _channel.invokeMethod<String>('pickImage');

  static Future<List<String>> pickImages({int maxCount = 5}) async {
    final result = await _channel.invokeMethod<List<Object?>>(
      'pickImages',
      <String, Object?>{'maxCount': maxCount},
    );
    return result?.whereType<String>().toList(growable: false) ??
        const <String>[];
  }
}
