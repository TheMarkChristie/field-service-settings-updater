import 'package:flutter/foundation.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

/// New-content push via Firebase Cloud Messaging.
///
/// The app subscribes to broadcast topics; the Syndicate Pro plugin
/// publishes to those topics (FCM HTTP v1) when new content is
/// published — the `new_content` topic reaches everyone, and each
/// `cat_<id>` topic reaches members who follow that category. No
/// per-device token management is needed on the WordPress side.
///
/// Firebase must be configured first (google-services.json +
/// firebase_options.dart); until then [init] no-ops gracefully so the
/// rest of the app runs.
class PushService {
  static const topicAll = 'new_content';

  bool _ready = false;
  bool get isReady => _ready;

  Future<void> init() async {
    try {
      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission();
      // Everyone hears about new content by default; the Settings toggle
      // and per-category follows refine this.
      await messaging.subscribeToTopic(topicAll);
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

  /// Turn the general "new content" notifications on or off.
  Future<void> setAllContent(bool on) async {
    if (!_ready) return;
    final messaging = FirebaseMessaging.instance;
    if (on) {
      await messaging.subscribeToTopic(topicAll);
    } else {
      await messaging.unsubscribeFromTopic(topicAll);
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
