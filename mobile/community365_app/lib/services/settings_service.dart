import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../config.dart';

/// User settings: theme mode and how many latest items to keep offline
/// per content type. Backed by shared_preferences.
class SettingsService extends ChangeNotifier {
  static const _kThemeMode = 'theme_mode';
  static const _kOfflineLimit = 'offline_limit';
  static const _kOfflineEnabled = 'offline_enabled';

  late SharedPreferences _prefs;
  ThemeMode _themeMode = ThemeMode.system;
  int _offlineLimit = Config.defaultOfflineLimit;
  bool _offlineEnabled = true;

  ThemeMode get themeMode => _themeMode;
  int get offlineLimit => _offlineLimit;
  bool get offlineEnabled => _offlineEnabled;

  Future<void> load() async {
    _prefs = await SharedPreferences.getInstance();
    _themeMode = ThemeMode.values[
        (_prefs.getInt(_kThemeMode) ?? ThemeMode.system.index)
            .clamp(0, ThemeMode.values.length - 1)];
    _offlineLimit = _prefs.getInt(_kOfflineLimit) ?? Config.defaultOfflineLimit;
    _offlineEnabled = _prefs.getBool(_kOfflineEnabled) ?? true;
    notifyListeners();
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    _themeMode = mode;
    await _prefs.setInt(_kThemeMode, mode.index);
    notifyListeners();
  }

  Future<void> setOfflineLimit(int limit) async {
    _offlineLimit = limit;
    await _prefs.setInt(_kOfflineLimit, limit);
    notifyListeners();
  }

  Future<void> setOfflineEnabled(bool enabled) async {
    _offlineEnabled = enabled;
    await _prefs.setBool(_kOfflineEnabled, enabled);
    notifyListeners();
  }
}
