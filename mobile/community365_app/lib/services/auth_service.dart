import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Holds the member's WordPress credentials (username + Application
/// Password) in the platform secure store and exposes the HTTP Basic
/// auth header the API expects. Anonymous users have no credentials and
/// receive the public last-5-days feed.
class AuthService extends ChangeNotifier {
  static const _kUser = 'wp_user';
  static const _kPass = 'wp_app_password';

  final _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  String? _username;
  bool _loaded = false;

  bool get isLoggedIn => _username != null;
  bool get isLoaded => _loaded;
  String? get username => _username;

  /// Load any stored credentials at startup.
  Future<void> load() async {
    _username = await _storage.read(key: _kUser);
    _loaded = true;
    notifyListeners();
  }

  /// The Basic auth header value, or null when anonymous.
  Future<String?> authHeader() async {
    final user = await _storage.read(key: _kUser);
    final pass = await _storage.read(key: _kPass);
    if (user == null || pass == null) return null;
    // Application Passwords are shown with spaces for readability; WP
    // ignores them, and stripping keeps the base64 clean.
    final token = base64Encode(utf8.encode('$user:${pass.replaceAll(' ', '')}'));
    return 'Basic $token';
  }

  /// Store credentials after a successful verification.
  Future<void> signIn(String username, String appPassword) async {
    await _storage.write(key: _kUser, value: username);
    await _storage.write(key: _kPass, value: appPassword);
    _username = username;
    notifyListeners();
  }

  /// Forget credentials.
  Future<void> signOut() async {
    await _storage.delete(key: _kUser);
    await _storage.delete(key: _kPass);
    _username = null;
    notifyListeners();
  }
}
