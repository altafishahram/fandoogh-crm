import 'package:flutter/material.dart';
import 'package:fandoogh_crm/core/widgets/glass_panel.dart';

final class SectionCard extends StatelessWidget {
  const SectionCard({
    required this.title,
    required this.child,
    this.action,
    super.key,
  });
  final String title;
  final Widget child;
  final Widget? action;

  @override
  Widget build(BuildContext context) => GlassPanel(
    padding: const EdgeInsets.all(18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Row(
          children: <Widget>[
            Expanded(
              child: Text(
                title,
                style: Theme.of(context).textTheme.titleMedium,
              ),
            ),
            ?action,
          ],
        ),
        const SizedBox(height: 12),
        child,
      ],
    ),
  );
}
