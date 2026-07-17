import 'package:flutter/material.dart';

/// The 365 Community palette — black, orange, white — matching the
/// Community 365 website theme. Light and dark variants both provided;
/// the app follows the device setting by default.
class AppTheme {
  AppTheme._();

  static const Color orange = Color(0xFFF97316); // primary / accent
  static const Color orangeDark = Color(0xFFD97706); // pressed / visited
  static const Color ink = Color(0xFF0C0D12); // site background (dark)
  static const Color surfaceDark = Color(0xFF16181F);
  static const Color borderDark = Color(0xFF262A36);
  static const Color textDark = Color(0xFFF2F3F7);
  static const Color textSoftDark = Color(0xFF9AA1B2);

  static ThemeData get dark {
    const scheme = ColorScheme.dark(
      primary: orange,
      onPrimary: Colors.white,
      secondary: orange,
      surface: surfaceDark,
      onSurface: textDark,
    );
    return _build(
      brightness: Brightness.dark,
      scheme: scheme,
      scaffold: ink,
      surface: surfaceDark,
      border: borderDark,
      textSoft: textSoftDark,
    );
  }

  static ThemeData get light {
    const scheme = ColorScheme.light(
      primary: orange,
      onPrimary: Colors.white,
      secondary: orangeDark,
      surface: Colors.white,
      onSurface: Color(0xFF16181F),
    );
    return _build(
      brightness: Brightness.light,
      scheme: scheme,
      scaffold: const Color(0xFFF4F5F7),
      surface: Colors.white,
      border: const Color(0xFFE2E5EA),
      textSoft: const Color(0xFF5A6270),
    );
  }

  static ThemeData _build({
    required Brightness brightness,
    required ColorScheme scheme,
    required Color scaffold,
    required Color surface,
    required Color border,
    required Color textSoft,
  }) {
    final base = ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: scaffold,
      fontFamily: 'Roboto',
    );
    return base.copyWith(
      appBarTheme: AppBarTheme(
        backgroundColor: surface,
        foregroundColor: scheme.onSurface,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: scheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.3,
        ),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: BorderSide(color: border),
        ),
        clipBehavior: Clip.antiAlias,
        margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      ),
      chipTheme: base.chipTheme.copyWith(
        backgroundColor: surface,
        side: BorderSide(color: border),
        labelStyle: TextStyle(color: textSoft, fontWeight: FontWeight.w600),
      ),
      dividerColor: border,
      textTheme: base.textTheme.copyWith(
        bodyMedium: base.textTheme.bodyMedium?.copyWith(color: scheme.onSurface),
        titleLarge: base.textTheme.titleLarge?.copyWith(
          fontWeight: FontWeight.w800,
          letterSpacing: -0.3,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: orange,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
          textStyle: const TextStyle(
            fontWeight: FontWeight.w800,
            letterSpacing: 0.3,
          ),
        ),
      ),
      extensions: [
        AppColors(textSoft: textSoft, border: border, surface: surface),
      ],
    );
  }
}

/// Extra semantic colours not covered by [ColorScheme].
class AppColors extends ThemeExtension<AppColors> {
  final Color textSoft;
  final Color border;
  final Color surface;

  const AppColors({
    required this.textSoft,
    required this.border,
    required this.surface,
  });

  static AppColors of(BuildContext context) =>
      Theme.of(context).extension<AppColors>()!;

  @override
  AppColors copyWith({Color? textSoft, Color? border, Color? surface}) =>
      AppColors(
        textSoft: textSoft ?? this.textSoft,
        border: border ?? this.border,
        surface: surface ?? this.surface,
      );

  @override
  AppColors lerp(ThemeExtension<AppColors>? other, double t) {
    if (other is! AppColors) return this;
    return AppColors(
      textSoft: Color.lerp(textSoft, other.textSoft, t)!,
      border: Color.lerp(border, other.border, t)!,
      surface: Color.lerp(surface, other.surface, t)!,
    );
  }
}
