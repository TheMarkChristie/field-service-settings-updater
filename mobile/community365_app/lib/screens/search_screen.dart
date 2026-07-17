import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config.dart';
import '../models/post.dart';
import '../services/api_client.dart';
import '../theme.dart';
import '../widgets/post_card.dart';
import 'article_screen.dart';

/// Full-text search across all four content types. Debounced; each type
/// is queried and the results are merged newest-first.
class SearchScreen extends StatefulWidget {
  const SearchScreen({super.key});

  @override
  State<SearchScreen> createState() => _SearchScreenState();
}

class _SearchScreenState extends State<SearchScreen> {
  final _controller = TextEditingController();
  Timer? _debounce;
  List<Post> _results = [];
  bool _loading = false;
  bool _searched = false;
  String? _error;

  @override
  void dispose() {
    _debounce?.cancel();
    _controller.dispose();
    super.dispose();
  }

  void _onChanged(String value) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () => _run(value));
  }

  Future<void> _run(String query) async {
    final q = query.trim();
    if (q.length < 2) {
      setState(() {
        _results = [];
        _searched = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
      _searched = true;
    });
    final api = context.read<ApiClient>();
    try {
      // Query every content type and merge.
      final pages = await Future.wait(
        Config.contentTypes.map((t) => api.feed(t.key, search: q)),
      );
      final merged = pages.expand((p) => p.items).toList()
        ..sort((a, b) => (b.date ?? DateTime(0)).compareTo(a.date ?? DateTime(0)));
      if (!mounted) return;
      setState(() {
        _results = merged;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Search failed. Check your connection and try again.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    return Scaffold(
      appBar: AppBar(
        title: TextField(
          controller: _controller,
          autofocus: true,
          textInputAction: TextInputAction.search,
          onChanged: _onChanged,
          onSubmitted: _run,
          decoration: InputDecoration(
            hintText: 'Search the community…',
            border: InputBorder.none,
            hintStyle: TextStyle(color: colors.textSoft),
          ),
          style: const TextStyle(fontSize: 18),
        ),
        actions: [
          if (_controller.text.isNotEmpty)
            IconButton(
              icon: const Icon(Icons.clear),
              onPressed: () {
                _controller.clear();
                _run('');
              },
            ),
        ],
      ),
      body: _buildBody(colors),
    );
  }

  Widget _buildBody(AppColors colors) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(_error!,
              textAlign: TextAlign.center,
              style: TextStyle(color: colors.textSoft)),
        ),
      );
    }
    if (!_searched) {
      return Center(
        child: Text('Type at least two letters to search.',
            style: TextStyle(color: colors.textSoft)),
      );
    }
    if (_results.isEmpty) {
      return Center(
        child: Text('No results.', style: TextStyle(color: colors.textSoft)),
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: _results.length,
      itemBuilder: (context, i) => PostCard(
        post: _results[i],
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ArticleScreen(post: _results[i])),
        ),
      ),
    );
  }
}
