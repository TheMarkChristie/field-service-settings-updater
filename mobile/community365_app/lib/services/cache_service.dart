import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:sqflite/sqflite.dart';

import '../models/post.dart';

/// Offline store for the latest items per content type. On each
/// successful online fetch of page 1 we replace that type's cache and
/// trim it to the member's chosen limit; when offline, the feed reads
/// from here.
class CacheService {
  static const _dbName = 'community365_cache.db';
  static const _table = 'posts';
  static const _bookmarks = 'bookmarks';
  Database? _db;

  Future<Database> get _database async {
    if (_db != null) return _db!;
    // A per-platform writable location: works on Android, iOS, and desktop
    // (getDatabasesPath is Android/iOS only).
    final dir = await getApplicationSupportDirectory();
    _db = await openDatabase(
      p.join(dir.path, _dbName),
      version: 2,
      onCreate: (db, version) async {
        await _createPosts(db);
        await _createBookmarks(db);
      },
      onUpgrade: (db, oldVersion, newVersion) async {
        if (oldVersion < 2) await _createBookmarks(db);
      },
    );
    return _db!;
  }

  Future<void> _createPosts(Database db) async {
    await db.execute('''
      CREATE TABLE $_table (
        id INTEGER NOT NULL,
        type TEXT NOT NULL,
        title TEXT, excerpt TEXT, content TEXT, date TEXT,
        link TEXT, source_url TEXT, image TEXT, author TEXT,
        paid INTEGER DEFAULT 0, categories TEXT,
        audio_url TEXT, duration TEXT, video_id TEXT,
        event_start TEXT, event_end TEXT, event_location TEXT, event_url TEXT,
        cached_at INTEGER NOT NULL,
        PRIMARY KEY (id, type)
      )
    ''');
    await db.execute('CREATE INDEX idx_type ON $_table (type, cached_at)');
  }

  Future<void> _createBookmarks(Database db) async {
    // Same columns as $_table plus saved_at; kept separate so the offline
    // cache trim never removes something the member deliberately saved.
    await db.execute('''
      CREATE TABLE $_bookmarks (
        id INTEGER NOT NULL,
        type TEXT NOT NULL,
        title TEXT, excerpt TEXT, content TEXT, date TEXT,
        link TEXT, source_url TEXT, image TEXT, author TEXT,
        paid INTEGER DEFAULT 0, categories TEXT,
        audio_url TEXT, duration TEXT, video_id TEXT,
        event_start TEXT, event_end TEXT, event_location TEXT, event_url TEXT,
        saved_at INTEGER NOT NULL,
        PRIMARY KEY (id, type)
      )
    ''');
  }

  /// Replace the cached items for a type with a fresh set, newest first,
  /// keeping at most [limit] rows.
  Future<void> replaceType(String type, List<Post> posts, int limit) async {
    final db = await _database;
    final now = DateTime.now().millisecondsSinceEpoch;
    await db.transaction((txn) async {
      await txn.delete(_table, where: 'type = ?', whereArgs: [type]);
      final capped = posts.take(limit).toList();
      final batch = txn.batch();
      for (var i = 0; i < capped.length; i++) {
        final row = capped[i].toCacheRow();
        // Preserve feed order via cached_at (descending index).
        row['cached_at'] = now - i;
        batch.insert(_table, row, conflictAlgorithm: ConflictAlgorithm.replace);
      }
      await batch.commit(noResult: true);
    });
  }

  /// Read the cached items for a type, newest first.
  Future<List<Post>> readType(String type) async {
    final db = await _database;
    final rows = await db.query(
      _table,
      where: 'type = ?',
      whereArgs: [type],
      orderBy: 'cached_at DESC',
    );
    return rows.map(Post.fromCacheRow).toList();
  }

  /// A single cached item (for the reader when offline).
  Future<Post?> read(int id, String type) async {
    final db = await _database;
    final rows = await db.query(
      _table,
      where: 'id = ? AND type = ?',
      whereArgs: [id, type],
      limit: 1,
    );
    return rows.isEmpty ? null : Post.fromCacheRow(rows.first);
  }

  /// Trim every type down to [limit] rows (after the limit is lowered).
  Future<void> trimAll(int limit) async {
    final db = await _database;
    final types = await db.rawQuery('SELECT DISTINCT type FROM $_table');
    for (final t in types) {
      final type = t['type'] as String;
      final keep = await db.query(
        _table,
        columns: ['id'],
        where: 'type = ?',
        whereArgs: [type],
        orderBy: 'cached_at DESC',
        limit: limit,
      );
      final keepIds = keep.map((r) => r['id']).toList();
      if (keepIds.isEmpty) continue;
      final placeholders = List.filled(keepIds.length, '?').join(',');
      await db.delete(
        _table,
        where: 'type = ? AND id NOT IN ($placeholders)',
        whereArgs: [type, ...keepIds],
      );
    }
  }

  /// Save a post to bookmarks.
  Future<void> addBookmark(Post post) async {
    final db = await _database;
    final row = post.toCacheRow()..remove('cached_at');
    row['saved_at'] = DateTime.now().millisecondsSinceEpoch;
    await db.insert(_bookmarks, row,
        conflictAlgorithm: ConflictAlgorithm.replace);
  }

  /// Remove a bookmark.
  Future<void> removeBookmark(int id, String type) async {
    final db = await _database;
    await db.delete(_bookmarks,
        where: 'id = ? AND type = ?', whereArgs: [id, type]);
  }

  /// Whether a post is bookmarked.
  Future<bool> isBookmarked(int id, String type) async {
    final db = await _database;
    final rows = await db.query(
      _bookmarks,
      columns: ['id'],
      where: 'id = ? AND type = ?',
      whereArgs: [id, type],
      limit: 1,
    );
    return rows.isNotEmpty;
  }

  /// All bookmarks, newest-saved first.
  Future<List<Post>> bookmarks() async {
    final db = await _database;
    final rows = await db.query(_bookmarks, orderBy: 'saved_at DESC');
    return rows.map(Post.fromCacheRow).toList();
  }

  /// Wipe the offline cache (Settings → clear offline content). Bookmarks
  /// are deliberately kept — they're the member's own saved list.
  Future<void> clear() async {
    final db = await _database;
    await db.delete(_table);
  }

  /// Rough count of cached items, for the Settings summary.
  Future<int> count() async {
    final db = await _database;
    final r = await db.rawQuery('SELECT COUNT(*) AS c FROM $_table');
    return (r.first['c'] as int?) ?? 0;
  }
}
