import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/post.dart';
import '../services/cache_service.dart';
import '../theme.dart';

/// Native reader for one item. Blog/podcast/video/event share a layout;
/// type-specific extras (event details, external media buttons, source
/// attribution, paid disclosure) are added where relevant.
class ArticleScreen extends StatefulWidget {
  final Post post;
  const ArticleScreen({super.key, required this.post});

  @override
  State<ArticleScreen> createState() => _ArticleScreenState();
}

class _ArticleScreenState extends State<ArticleScreen> {
  bool _bookmarked = false;
  bool _bookmarkLoaded = false;

  Post get post => widget.post;

  @override
  void initState() {
    super.initState();
    context.read<CacheService>().isBookmarked(post.id, post.type).then((v) {
      if (mounted) setState(() {
        _bookmarked = v;
        _bookmarkLoaded = true;
      });
    });
  }

  Future<void> _toggleBookmark() async {
    final cache = context.read<CacheService>();
    if (_bookmarked) {
      await cache.removeBookmark(post.id, post.type);
    } else {
      await cache.addBookmark(post);
    }
    if (mounted) setState(() => _bookmarked = !_bookmarked);
  }

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(_kind(post.type)),
        actions: [
          IconButton(
            tooltip: _bookmarked ? 'Remove from saved' : 'Save',
            icon: Icon(_bookmarked ? Icons.bookmark : Icons.bookmark_border),
            onPressed: _bookmarkLoaded ? _toggleBookmark : null,
          ),
          IconButton(
            tooltip: 'Open in browser',
            icon: const Icon(Icons.open_in_new),
            onPressed: post.link.isEmpty ? null : () => _open(post.link),
          ),
        ],
      ),
      body: ListView(
        children: [
          if (post.hasImage)
            Stack(
              children: [
                CachedNetworkImage(
                  imageUrl: post.image,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
                if (post.paid)
                  Positioned(top: 12, left: 12, child: _paidBadge()),
              ],
            ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  post.title,
                  style: const TextStyle(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    height: 1.25,
                    letterSpacing: -0.4,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    if (post.author.isNotEmpty)
                      Text('by ${post.author}',
                          style: TextStyle(color: colors.textSoft)),
                    if (post.author.isNotEmpty && post.date != null)
                      Text('  ·  ', style: TextStyle(color: colors.textSoft)),
                    if (post.date != null)
                      Text(DateFormat.yMMMMd().format(post.date!),
                          style: TextStyle(color: colors.textSoft)),
                  ],
                ),
                const SizedBox(height: 16),
                if (post.paid) _paidBanner(colors),
                if (post.type == 'event') _eventDetails(context, colors),
                _mediaButtons(context),
                const SizedBox(height: 4),
                HtmlWidget(
                  post.content,
                  textStyle: const TextStyle(fontSize: 17, height: 1.6),
                  onTapUrl: (url) async {
                    await _open(url);
                    return true;
                  },
                ),
                const SizedBox(height: 24),
                if (post.sourceUrl.isNotEmpty) _sourceBox(colors),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _eventDetails(BuildContext context, AppColors colors) {
    final rows = <Widget>[];
    void add(IconData icon, String label) => rows.add(Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(
            children: [
              Icon(icon, size: 18, color: AppTheme.orange),
              const SizedBox(width: 10),
              Expanded(child: Text(label)),
            ],
          ),
        ));

    if (post.eventStart != null) {
      final end = post.eventEnd != null && post.eventEnd != post.eventStart
          ? ' – ${post.eventEnd}'
          : '';
      add(Icons.event, '${post.eventStart}$end');
    }
    if (post.eventLocation != null) add(Icons.place, post.eventLocation!);
    if (rows.isEmpty) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: colors.border),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        ...rows,
        if (post.eventUrl != null)
          FilledButton.icon(
            onPressed: () => _open(post.eventUrl!),
            icon: const Icon(Icons.confirmation_num_outlined),
            label: const Text('Tickets & details'),
          ),
      ]),
    );
  }

  /// External-media buttons (podcasts and videos open outside the app).
  Widget _mediaButtons(BuildContext context) {
    if (post.type == 'podcast' && post.audioUrl != null) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: FilledButton.icon(
          onPressed: () => _open(post.audioUrl!),
          icon: const Icon(Icons.play_circle_outline),
          label: Text(post.duration != null
              ? 'Play episode · ${post.duration}'
              : 'Play episode'),
        ),
      );
    }
    if (post.type == 'video' && post.videoId != null) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: FilledButton.icon(
          onPressed: () =>
              _open('https://www.youtube.com/watch?v=${post.videoId}'),
          icon: const Icon(Icons.play_arrow),
          label: const Text('Watch on YouTube'),
        ),
      );
    }
    return const SizedBox.shrink();
  }

  Widget _sourceBox(AppColors colors) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: colors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border(left: BorderSide(color: AppTheme.orange, width: 4)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Republished with the author’s permission.',
            style: TextStyle(color: colors.textSoft, fontSize: 13)),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => _open(post.sourceUrl),
          icon: const Icon(Icons.open_in_new, size: 18),
          label: const Text('Read the original'),
        ),
      ]),
    );
  }

  Widget _paidBanner(AppColors colors) => Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AppTheme.orange.withValues(alpha: 0.14),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: AppTheme.orange),
        ),
        child: Row(children: [
          _paidBadge(),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'This post is paid-for content — its placement was paid for.',
              style: TextStyle(fontSize: 13),
            ),
          ),
        ]),
      );

  Widget _paidBadge() => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
        decoration: BoxDecoration(
          color: AppTheme.orange,
          borderRadius: BorderRadius.circular(999),
        ),
        child: const Text('PAID CONTENT',
            style: TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.6)),
      );

  Future<void> _open(String url) async {
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  String _kind(String type) {
    switch (type) {
      case 'event':
        return 'Event';
      case 'podcast':
        return 'Podcast';
      case 'video':
        return 'Video';
      default:
        return 'Article';
    }
  }
}
