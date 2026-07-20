/// Club branding parsed from the `/me` `club` payload (name + full brand
/// pack). The app themes itself entirely from this, so a club restyles the
/// app from the website with no app release (T46 / P136).
library;

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;

class ClubBrand {
  ClubBrand(this._club, {this.fontFamily});

  final Map<String, dynamic> _club;

  /// Runtime-registered club font family, if one loaded successfully.
  final String? fontFamily;

  String get name => _string('name') ?? 'Fan Owners';
  String? get tagline => _string('tagline');
  String? get badge => _string('badge') ?? _string('badge_svg');
  String? get wordmark => _string('wordmark');
  String? get appIcon => _string('app_icon');
  String? get fontFile => _string('font_file');
  String? get fontName => _string('font_name');

  Color get primary => _color('primary') ?? const Color(0xFF1A1A2E);
  Color get secondary => _color('secondary') ?? Colors.white;
  Color get tertiary => _color('tertiary') ?? const Color(0xFFE2B007);

  /// A full colour scheme seeded from the club primary, with the club
  /// primary and accent pinned so the palette reads as the club's.
  ColorScheme scheme(Brightness brightness) {
    return ColorScheme.fromSeed(
      seedColor: primary,
      brightness: brightness,
    ).copyWith(
      primary: brightness == Brightness.light ? primary : null,
      secondary: tertiary,
    );
  }

  ThemeData theme(Brightness brightness) {
    final scheme = this.scheme(brightness);
    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      fontFamily: fontFamily,
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.primary,
        foregroundColor: scheme.onPrimary,
        centerTitle: false,
      ),
    );
  }

  String? _string(String key) {
    final v = _club[key];
    return (v is String && v.trim().isNotEmpty) ? v : null;
  }

  Color? _color(String key) {
    final hex = _club[key];
    if (hex is! String || !hex.startsWith('#') || hex.length != 7) return null;
    return Color(int.parse('FF${hex.substring(1)}', radix: 16));
  }
}

/// Best-effort runtime load of the club's brand font. Flutter's FontLoader
/// handles TrueType/OpenType; web font formats (woff/woff2) are skipped so
/// a mismatched upload never breaks theming — the app just keeps the
/// system font. Returns the family name to use, or null.
Future<String?> loadClubFont(String? url, String? family) async {
  if (url == null || family == null || family.trim().isEmpty) return null;
  final lower = url.toLowerCase();
  if (!lower.endsWith('.ttf') && !lower.endsWith('.otf')) return null;
  try {
    final res =
        await http.get(Uri.parse(url)).timeout(const Duration(seconds: 15));
    if (res.statusCode != 200 || res.bodyBytes.isEmpty) return null;
    final loader = FontLoader(family)
      ..addFont(Future.value(res.bodyBytes.buffer.asByteData()));
    await loader.load();
    return family;
  } catch (_) {
    return null;
  }
}
