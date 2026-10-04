import 'package:flutter/material.dart';

abstract final class AppTheme {
  static const background = Color(0xFFF4FBF9);
  static const primary = Color(0xFF0F766E);
  static const primarySoft = Color(0xFFD9F5EE);
  static const accent = Color(0xFF115E59);
  static const border = Color(0xFFDCECE8);
  static const blue = Color(0xFF0369A1);
  static const violet = Color(0xFF7557B7);
  static const orange = Color(0xFFB45309);
  static const coral = Color(0xFFC2414F);

  static ThemeData get light => _build(Brightness.light);

  static ThemeData get dark => _build(Brightness.dark);

  static ThemeData _build(Brightness brightness) {
    final dark = brightness == Brightness.dark;
    final colorScheme =
        ColorScheme.fromSeed(
          seedColor: primary,
          brightness: brightness,
          surface: dark ? const Color(0xFF172725) : Colors.white,
        ).copyWith(
          primary: primary,
          onPrimary: Colors.white,
          secondary: blue,
          tertiary: violet,
          error: coral,
          surfaceContainerLowest: dark ? const Color(0xFF0F1C1B) : background,
          surfaceContainerLow: dark
              ? const Color(0xFF1D302E)
              : const Color(0xFFF7FCFB),
          outlineVariant: dark ? const Color(0xFF2B4541) : border,
        );

    final rounded = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(20),
      side: BorderSide(color: colorScheme.outlineVariant),
    );

    return ThemeData(
      brightness: brightness,
      colorScheme: colorScheme,
      fontFamily: 'YekanBakh',
      useMaterial3: true,
      scaffoldBackgroundColor: dark ? const Color(0xFF0F1C1B) : background,
      canvasColor: dark ? const Color(0xFF0F1C1B) : background,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: dark ? const Color(0xFF0F1C1B) : background,
        surfaceTintColor: Colors.transparent,
        foregroundColor: dark ? const Color(0xFFEEFAF7) : accent,
        titleTextStyle: TextStyle(
          color: dark ? const Color(0xFFEEFAF7) : accent,
          fontFamily: 'YekanBakh',
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
        fillColor: dark ? const Color(0xFF1D302E) : const Color(0xFFF7FCFB),
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
        height: 70,
        elevation: 0,
        backgroundColor: colorScheme.surface,
        indicatorColor: dark ? const Color(0xFF173F3A) : primarySoft,
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(
            color: dark ? const Color(0xFFEEFAF7) : accent,
            fontWeight: FontWeight.w700,
            fontSize: 11,
          ),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        elevation: 3,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(17)),
        ),
      ),
      searchBarTheme: SearchBarThemeData(
        elevation: const WidgetStatePropertyAll(0),
        backgroundColor: WidgetStatePropertyAll(colorScheme.surface),
        side: WidgetStatePropertyAll(
          BorderSide(color: colorScheme.outlineVariant),
        ),
        shape: const WidgetStatePropertyAll(
          RoundedRectangleBorder(
            borderRadius: BorderRadius.all(Radius.circular(17)),
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
        side: BorderSide(color: colorScheme.outlineVariant),
        selectedColor: dark ? const Color(0xFF173F3A) : primarySoft,
        labelStyle: const TextStyle(fontWeight: FontWeight.w700),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: dark ? const Color(0xFF1D302E) : Colors.white,
        contentTextStyle: TextStyle(
          color: colorScheme.onSurface,
          fontFamily: 'YekanBakh',
          fontWeight: FontWeight.w700,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
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
