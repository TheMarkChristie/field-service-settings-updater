import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

import '../models/post.dart';

/// Offline store for the latest items per content type. On each
/// successful online fetch of page 1 we replace that type's cache and
/// trim it to the member's chosen limit; when offline, the feed reads
/// from here.
class CacheService {
  static const _dbName = 'community365_cache.db';
  static const _table = 'posts';
  Database? _db;

  Future<Database> get _database async {
    if (_db != null) return _db!;
    final dir = await getDatabasesPath();
    _db = await openDatabase(
      p.join(dir, _dbName),
      version: 1,
      onCreate: (db, version) async {
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
      },
    );
    return _db!;
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

  /// Wipe everything (Settings → clear offline content).
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
