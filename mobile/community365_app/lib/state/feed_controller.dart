import 'package:flutter/foundation.dart';

import '../models/post.dart';
import '../services/api_client.dart';
import '../services/feed_repository.dart';

/// Drives one content-type tab: initial load, pagination, refresh, and
/// error/empty/offline state. One instance per tab.
class FeedController extends ChangeNotifier {
  final FeedRepository repo;
  final String type;

  FeedController({required this.repo, required this.type});

  final List<Post> _items = [];
  List<Post> get items => List.unmodifiable(_items);

  bool _loading = false;
  bool _loadingMore = false;
  bool _hasMore = true;
  bool _fromCache = false;
  int _page = 0;
  String? _error;
  bool _initialised = false;

  bool get loading => _loading;
  bool get loadingMore => _loadingMore;
  bool get hasMore => _hasMore;
  bool get fromCache => _fromCache;
  String? get error => _error;
  bool get isEmpty => _items.isEmpty && !_loading && _error == null;

  /// Load the first page (only once unless [force]).
  Future<void> loadInitial({bool force = false}) async {
    if (_initialised && !force) return;
    _initialised = true;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final result = await repo.load(type, page: 1);
      _items
        ..clear()
        ..addAll(result.items);
      _page = 1;
      _hasMore = result.hasMore;
      _fromCache = result.fromCache;
    } on ApiException catch (e) {
      _error = e.message;
    } catch (_) {
      _error = 'Couldn\'t load content. Check your connection and try again.';
    } finally {
      _loading = false;
      notifyListeners();
    }
  }

  /// Pull-to-refresh.
  Future<void> refresh() => loadInitial(force: true);

  /// Load the next page (infinite scroll).
  Future<void> loadMore() async {
    if (_loadingMore || !_hasMore || _loading || _fromCache) return;
    _loadingMore = true;
    notifyListeners();
    try {
      final result = await repo.load(type, page: _page + 1);
      _items.addAll(result.items);
      _page = result.page;
      _hasMore = result.hasMore;
    } catch (_) {
      _hasMore = false; // stop trying on error
    } finally {
      _loadingMore = false;
      notifyListeners();
    }
  }
}
