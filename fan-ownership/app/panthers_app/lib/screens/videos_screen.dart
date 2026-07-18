import 'package:flutter/material.dart';

import '../api/prx3_api.dart';

/// Panthers TV library (FO-311): typed browsing + search; playback embeds
/// the signed Cloudflare token / gated media URL at build-out.
class VideosScreen extends StatefulWidget {
  const VideosScreen({super.key, required this.api});
  final Prx3Api api;

  @override
  State<VideosScreen> createState() => _VideosScreenState();
}

class _VideosScreenState extends State<VideosScreen> {
  String? _type;
  String _search = '';
  late Future<List<dynamic>> _videos;

  static const _types = {
    null: 'All',
    'match-replay': 'Replays',
    'training': 'Training',
    'post-match-interview': 'Interviews',
    'behind-the-scenes': 'Behind the scenes',
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  void _load() {
    _videos = widget.api.videos(type: _type, search: _search);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(8),
          child: TextField(
            decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search), labelText: 'Search the library'),
            onSubmitted: (value) => setState(() {
              _search = value;
              _load();
            }),
          ),
        ),
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            children: [
              for (final entry in _types.entries)
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: ChoiceChip(
                    label: Text(entry.value),
                    selected: _type == entry.key,
                    onSelected: (_) => setState(() {
                      _type = entry.key;
                      _load();
                    }),
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: FutureBuilder<List<dynamic>>(
            future: _videos,
            builder: (context, snapshot) {
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              return ListView(
                children: [
                  for (final video in snapshot.data!)
                    ListTile(
                      leading: const Icon(Icons.play_circle_outline),
                      title: Text(video['title'] as String? ?? ''),
                      subtitle: Text(((video['types'] as List?) ?? []).join(', ')),
                      trailing: (video['resume'] as int? ?? 0) > 0
                          ? const Icon(Icons.history)
                          : null,
                    ),
                ],
              );
            },
          ),
        ),
      ],
    );
  }
}
