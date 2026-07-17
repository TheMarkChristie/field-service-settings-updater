import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'app.dart';
import 'services/api_client.dart';
import 'services/auth_service.dart';
import 'services/cache_service.dart';
import 'services/feed_repository.dart';
import 'services/push_service.dart';
import 'services/settings_service.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Firebase is optional at first run — the app works without it until
  // google-services.json + firebase_options.dart are added.
  try {
    await Firebase.initializeApp();
  } catch (_) {}

  final auth = AuthService();
  final settings = SettingsService();
  await Future.wait([auth.load(), settings.load()]);

  final cache = CacheService();
  final api = ApiClient(auth);
  final repo = FeedRepository(api: api, cache: cache, settings: settings);
  final push = PushService();
  await push.init();

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
