/// App-wide configuration for the 365 Community reader.
///
/// The app talks to the Syndicate Pro plugin's REST API
/// (`synpro/v1`) on the live site. Nothing here is secret — member
/// credentials live in secure storage, never in the bundle.
class Config {
  Config._();

  /// The WordPress site the app reads from.
  static const String siteUrl = 'https://365community.online';

  /// The Syndicate Pro REST namespace.
  static const String apiBase = '$siteUrl/wp-json/synpro/v1';

  /// Content types the app browses, in tab order. Values match the
  /// `type` query parameter the API accepts.
  static const List<ContentType> contentTypes = [
    ContentType(key: 'post', label: 'Blogs', icon: 0xe3e0), // article
    ContentType(key: 'event', label: 'Events', icon: 0xe878), // event
    ContentType(key: 'podcast', label: 'Podcasts', icon: 0xe0b1), // podcast
    ContentType(key: 'video', label: 'Videos', icon: 0xe04b), // videocam
  ];

  /// Default number of latest items kept for offline reading, per type.
  /// Members change this in Settings.
  static const int defaultOfflineLimit = 25;

  /// Allowed offline-limit choices shown in Settings.
  static const List<int> offlineLimitChoices = [10, 25, 50, 100, 200];

  /// Page size for feed requests (server caps at 50).
  static const int pageSize = 20;
}

/// A browsable content type (a bottom-nav tab).
class ContentType {
  final String key;
  final String label;
  final int icon; // Material icon code point.

  const ContentType({required this.key, required this.label, required this.icon});
}
