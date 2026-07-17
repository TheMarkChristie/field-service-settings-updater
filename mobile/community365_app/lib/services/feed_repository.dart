import 'api_client.dart';
import 'cache_service.dart';
import 'settings_service.dart';
import '../models/post.dart';

/// Bridges the API and the offline cache. Page 1 fetches live and
/// refreshes that type's cache (trimmed to the offline limit); on a
/// network error it falls back to the cache so members can still read.
class FeedRepository {
  final ApiClient api;
  final CacheService cache;
  final SettingsService settings;

  FeedRepository({required this.api, required this.cache, required this.settings});

  /// Result of a page load, plus whether it came from the cache.
  Future<FeedResult> load(String type, {int page = 1}) async {
    try {
      final result = await api.feed(type, page: page);
      if (page == 1 && settings.offlineEnabled) {
        // Refresh this type's offline copy from the freshest page.
        await cache.replaceType(type, result.items, settings.offlineLimit);
      }
      return FeedResult(
        items: result.items,
        page: result.page,
        hasMore: result.hasMore,
        fromCache: false,
        loggedIn: result.loggedIn,
      );
    } on ApiException {
      rethrow; // auth / server errors surface to the UI
    } catch (_) {
      // Network failure: serve the cache for page 1, empty otherwise.
      if (page == 1) {
        final cached = await cache.readType(type);
        return FeedResult(
          items: cached,
          page: 1,
          hasMore: false,
          fromCache: true,
          loggedIn: false,
        );
      }
      rethrow;
    }
  }

  /// A single item for the reader, cache-first when offline.
  Future<Post?> item(int id, String type) => cache.read(id, type);
}

class FeedResult {
  final List<Post> items;
  final int page;
  final bool hasMore;
  final bool fromCache;
  final bool loggedIn;

  const FeedResult({
    required this.items,
    required this.page,
    required this.hasMore,
    required this.fromCache,
    required this.loggedIn,
  });
}
