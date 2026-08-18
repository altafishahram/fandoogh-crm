import 'package:fandoogh_crm/core/theme/app_theme.dart';
import 'package:flutter/material.dart';

/// نام کلاس برای سازگاری کدهای فعلی حفظ شده است؛ ظاهر آن دیگر شیشه‌ای نیست.
final class GlassPanel extends StatelessWidget {
  const GlassPanel({
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.borderRadius = const BorderRadius.all(Radius.circular(22)),
    this.margin,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final BorderRadius borderRadius;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) => Container(
    margin: margin,
    padding: padding,
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.surface,
      borderRadius: borderRadius,
      border: Border.all(color: Theme.of(context).colorScheme.outlineVariant),
      boxShadow: <BoxShadow>[
        BoxShadow(
          color: AppTheme.accent.withValues(
            alpha: Theme.of(context).brightness == Brightness.dark ? .18 : .08,
          ),
          blurRadius: 24,
          offset: const Offset(0, 10),
        ),
      ],
    ),
    child: child,
  );
}

final class GlassBackdrop extends StatelessWidget {
  const GlassBackdrop({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: Theme.of(context).scaffoldBackgroundColor,
    child: child,
  );
}

final class BrandSignature extends StatelessWidget {
  const BrandSignature({this.compact = false, super.key});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Semantics(
      label: 'ملک بان، طراحی‌شده توسط فندوق استودیو',
      child: Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Container(
            width: compact ? 34 : 48,
            height: compact ? 34 : 48,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(compact ? 11 : 15),
              color: AppTheme.primary,
            ),
            child: Center(
              child: Text(
                'م',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: compact ? 16 : 23,
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'ملک بان',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: colors.onSurface,
                ),
              ),
              Text(
                'طراحی‌شده توسط فندوق استودیو',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
