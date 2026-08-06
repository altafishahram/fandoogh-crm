import 'package:flutter/material.dart';

final class TextInputDialog extends StatefulWidget {
  const TextInputDialog({
    required this.title,
    this.initialValue = '',
    this.label,
    this.maxLines = 1,
    this.obscureText = false,
    this.keyboardType,
    this.confirmLabel = 'ثبت',
    super.key,
  });

  final String title;
  final String initialValue;
  final String? label;
  final int maxLines;
  final bool obscureText;
  final TextInputType? keyboardType;
  final String confirmLabel;

  @override
  State<TextInputDialog> createState() => _TextInputDialogState();
}

final class _TextInputDialogState extends State<TextInputDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.initialValue);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: TextField(
      controller: _controller,
      autofocus: true,
      maxLines: widget.obscureText ? 1 : widget.maxLines,
      obscureText: widget.obscureText,
      keyboardType: widget.keyboardType,
      decoration: InputDecoration(labelText: widget.label),
    ),
    actions: <Widget>[
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('انصراف'),
      ),
      FilledButton(
        onPressed: () => Navigator.pop(context, _controller.text.trim()),
        child: Text(widget.confirmLabel),
      ),
    ],
  );
}
