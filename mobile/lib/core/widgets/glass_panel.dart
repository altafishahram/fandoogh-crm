import 'dart:ui';

import 'package:flutter/material.dart';

final class GlassPanel extends StatelessWidget {
  const GlassPanel({
    required this.child,
    this.padding = const EdgeInsets.all(20),
    this.borderRadius = const BorderRadius.all(Radius.circular(28)),
    this.margin,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final BorderRadius borderRadius;
  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final surface = dark ? const Color(0x99111C2F) : const Color(0xB8FFFFFF);
    final border = dark
        ? Colors.white.withValues(alpha: .14)
        : Colors.white.withValues(alpha: .80);

    return Container(
      margin: margin,
      decoration: BoxDecoration(
        borderRadius: borderRadius,
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: const Color(0xFF0F172A).withValues(alpha: dark ? .32 : .10),
            blurRadius: 44,
            offset: const Offset(0, 20),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: borderRadius,
        child: BackdropFilter(
          filter: ImageFilter.blur(sigmaX: 20, sigmaY: 20),
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: surface,
              borderRadius: borderRadius,
              border: Border.all(color: border),
              gradient: LinearGradient(
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
                colors: <Color>[
                  Colors.white.withValues(alpha: dark ? .09 : .36),
                  surface,
                ],
              ),
            ),
            child: Padding(padding: padding, child: child),
          ),
        ),
      ),
    );
  }
}

final class GlassBackdrop extends StatelessWidget {
  const GlassBackdrop({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topRight,
          end: Alignment.bottomLeft,
          colors: dark
              ? const <Color>[
                  Color(0xFF020617),
                  Color(0xFF0F172A),
                  Color(0xFF052E2B),
                ]
              : const <Color>[
                  Color(0xFFECFEFF),
                  Color(0xFFF8FAFC),
                  Color(0xFFF0FDF4),
                ],
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          Positioned(
            top: -100,
            right: -90,
            child: _GlowOrb(
              size: 310,
              color: const Color(
                0xFF2DD4BF,
              ).withValues(alpha: dark ? .16 : .25),
            ),
          ),
          Positioned(
            top: 230,
            left: -120,
            child: _GlowOrb(
              size: 360,
              color: const Color(
                0xFF38BDF8,
              ).withValues(alpha: dark ? .13 : .20),
            ),
          ),
          Positioned(
            bottom: -160,
            right: -70,
            child: _GlowOrb(
              size: 390,
              color: const Color(
                0xFFA78BFA,
              ).withValues(alpha: dark ? .11 : .16),
            ),
          ),
          child,
        ],
      ),
    );
  }
}

final class _GlowOrb extends StatelessWidget {
  const _GlowOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: ImageFiltered(
      imageFilter: ImageFilter.blur(sigmaX: 35, sigmaY: 35),
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(color: color, shape: BoxShape.circle),
      ),
    ),
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
            width: compact ? 34 : 46,
            height: compact ? 34 : 46,
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(compact ? 11 : 15),
              gradient: const LinearGradient(
                begin: Alignment.topRight,
                end: Alignment.bottomLeft,
                colors: <Color>[Color(0xFF059669), Color(0xFF0D9488)],
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: const Color(0xFF059669).withValues(alpha: .24),
                  blurRadius: 22,
                  offset: const Offset(0, 9),
                ),
              ],
            ),
            child: Center(
              child: Text(
                'م',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: compact ? 16 : 22,
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
