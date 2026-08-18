import 'package:flutter/material.dart';

abstract final class AppTheme {
  static const background = Color(0xFFF3F7EF);
  static const primary = Color(0xFF6F9A73);
  static const primarySoft = Color(0xFFA8C6AA);
  static const accent = Color(0xFF426448);
  static const border = Color(0xFFD5E2D2);

  static ThemeData get light => _build(Brightness.light);

  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final dark = brightness == Brightness.dark;
    final colorScheme =
        ColorScheme.fromSeed(
          seedColor: primary,
          brightness: brightness,
          surface: dark ? const Color(0xFF223126) : Colors.white,
        ).copyWith(
          primary: primary,
          secondary: primarySoft,
          tertiary: accent,
          surfaceContainerLowest: dark ? const Color(0xFF17231A) : background,
          outlineVariant: dark ? const Color(0xFF3D5141) : border,
        );

    final rounded = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(20),
      side: BorderSide(color: colorScheme.outlineVariant),
    );

    return ThemeData(
      brightness: brightness,
      colorScheme: colorScheme,
      fontFamily: 'Vazirmatn',
      useMaterial3: true,
      scaffoldBackgroundColor: dark ? const Color(0xFF17231A) : background,
      canvasColor: dark ? const Color(0xFF17231A) : background,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: dark ? const Color(0xFF17231A) : background,
        surfaceTintColor: Colors.transparent,
        foregroundColor: dark ? const Color(0xFFEDF4E9) : accent,
        titleTextStyle: TextStyle(
          color: dark ? const Color(0xFFEDF4E9) : accent,
          fontFamily: 'Vazirmatn',
          fontSize: 20,
          fontWeight: FontWeight.w900,
        ),
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: rounded,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: dark ? const Color(0xFF26372A) : const Color(0xFFFBFDF9),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: colorScheme.outlineVariant),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: colorScheme.outlineVariant),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: primary, width: 1.6),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w800),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: dark ? primarySoft : accent,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
          side: BorderSide(color: colorScheme.outlineVariant),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        elevation: 0,
        backgroundColor: colorScheme.surface,
        indicatorColor: primarySoft.withValues(alpha: dark ? .24 : .42),
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(
            color: dark ? const Color(0xFFEDF4E9) : accent,
            fontWeight: FontWeight.w700,
            fontSize: 11,
          ),
        ),
      ),
      dialogTheme: DialogThemeData(
        elevation: 0,
        backgroundColor: colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
      ),
      dividerTheme: DividerThemeData(color: colorScheme.outlineVariant),
    );
  }
}
