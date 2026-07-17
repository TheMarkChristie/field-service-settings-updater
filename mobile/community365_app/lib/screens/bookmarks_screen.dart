import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/post.dart';
import '../services/cache_service.dart';
import '../theme.dart';
import '../widgets/post_card.dart';
import 'article_screen.dart';

/// The member's saved articles (local-only; survives cache clears).
class BookmarksScreen extends StatefulWidget {
  const BookmarksScreen({super.key});

  @override
  State<BookmarksScreen> createState() => _BookmarksScreenState();
}

class _BookmarksScreenState extends State<BookmarksScreen> {
  late Future<List<Post>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<CacheService>().bookmarks();
  }

  void _reload() {
    setState(() => _future = context.read<CacheService>().bookmarks());
  }

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    return Scaffold(
      appBar: AppBar(title: const Text('Saved')),
      body: FutureBuilder<List<Post>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(32),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.bookmark_border,
                        size: 48, color: colors.textSoft),
                    const SizedBox(height: 12),
                    const Text('Nothing saved yet',
                        style: TextStyle(
                            fontSize: 18, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 6),
                    Text('Tap the bookmark icon on any article to save it here.',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: colors.textSoft)),
                  ],
                ),
              ),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: items.length,
            itemBuilder: (context, i) => PostCard(
              post: items[i],
              onTap: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute(
                      builder: (_) => ArticleScreen(post: items[i])),
                );
                _reload(); // reflect any un-save done in the reader
              },
            ),
          );
        },
      ),
    );
  }
}
