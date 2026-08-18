import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

Future<void> launchPhoneCall(BuildContext context, String number) async {
  final normalized = number.trim();
  if (normalized.isEmpty) return;

  var opened = false;
  try {
    opened = await launchUrl(
      Uri(scheme: 'tel', path: normalized),
      mode: LaunchMode.externalApplication,
    );
  } catch (_) {
    opened = false;
  }
  if (!opened && context.mounted) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('امکان بازکردن صفحه تماس وجود ندارد.')),
    );
  }
}
