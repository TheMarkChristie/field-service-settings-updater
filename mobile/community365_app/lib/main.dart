import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart';

import 'app.dart';
import 'services/api_client.dart';
import 'services/auth_service.dart';
import 'services/cache_service.dart';
import 'services/feed_repository.dart';
import 'services/push_service.dart';
import 'services/settings_service.dart';

/// Desktop (Windows/macOS/Linux) uses the FFI SQLite engine; mobile uses
/// the bundled sqflite. Guarded by kIsWeb so Platform is never touched on
/// web.
bool get _isDesktop =>
    !kIsWeb && (Platform.isWindows || Platform.isLinux || Platform.isMacOS);

/// Firebase Cloud Messaging is only supported on Android and iOS.
bool get _isMobile => !kIsWeb && (Platform.isAndroid || Platform.isIOS);

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Desktop needs the FFI implementation registered before any DB access.
  if (_isDesktop) {
    sqfliteFfiInit();
    databaseFactory = databaseFactoryFfi;
  }

  // Firebase/push run on mobile only. Optional at first run — the app works
  // without it until google-services.json + firebase_options.dart are added.
  if (_isMobile) {
    try {
      await Firebase.initializeApp();
    } catch (_) {}
  }

  final auth = AuthService();
  final settings = SettingsService();
  await Future.wait([auth.load(), settings.load()]);

  final cache = CacheService();
  final api = ApiClient(auth);
  final repo = FeedRepository(api: api, cache: cache, settings: settings);
  final push = PushService();
  if (_isMobile) {
    await push.init();
  }

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider.value(value: settings),
        Provider.value(value: api),
        Provider.value(value: cache),
        Provider.value(value: repo),
        Provider.value(value: push),
      ],
      child: const Community365App(),
    ),
  );
}
