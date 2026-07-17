import 'package:flutter/foundation.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

/// New-content push via Firebase Cloud Messaging.
///
/// The app subscribes to broadcast topics; the Syndicate Pro plugin (or
/// a small server hook) publishes to those topics when new content is
/// published. Topic-based push needs no per-device token management on
/// the WordPress side. Members can mute categories they don't follow.
///
/// Firebase must be configured first (google-services.json +
/// firebase_options.dart); until then [init] no-ops gracefully so the
/// rest of the app runs.
class PushService {
  static const _topicAll = 'all_content';

  bool _ready = false;

  Future<void> init() async {
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      await messaging.subscribeToTopic(_topicAll);
      _ready = true;
      if (kDebugMode) {
        FirebaseMessaging.onMessage.listen((m) {
          debugPrint('Push received: ${m.notification?.title}');
        });
      }
    } catch (e) {
      // Firebase not configured yet — the app still works without push.
      if (kDebugMode) debugPrint('Push disabled: $e');
    }
  }

  /// Follow/unfollow a category topic (e.g. `cat_12`).
  Future<void> setCategory(int categoryId, bool follow) async {
    if (!_ready) return;
    final topic = 'cat_$categoryId';
    final messaging = FirebaseMessaging.instance;
    if (follow) {
      await messaging.subscribeToTopic(topic);
    } else {
      await messaging.unsubscribeFromTopic(topic);
    }
  }
}
