/// A single piece of content from the API — a blog post, event,
/// podcast episode, or video. One shape covers all four; type-specific
/// fields are null when not applicable.
class Post {
  final int id;
  final String type; // post | event | podcast | video
  final String title;
  final String excerpt;
  final String content; // rendered HTML
  final DateTime? date;
  final String link; // canonical web URL
  final String sourceUrl; // original author's article (syndicated posts)
  final String image;
  final String author;
  final bool paid;
  final List<PostCategory> categories;

  // Podcast
  final String? audioUrl;
  final String? duration;

  // Video
  final String? videoId;

  // Event
  final String? eventStart;
  final String? eventEnd;
  final String? eventLocation;
  final String? eventUrl;

  const Post({
    required this.id,
    required this.type,
    required this.title,
    required this.excerpt,
    required this.content,
    required this.date,
    required this.link,
    required this.sourceUrl,
    required this.image,
    required this.author,
    required this.paid,
    required this.categories,
    this.audioUrl,
    this.duration,
    this.videoId,
    this.eventStart,
    this.eventEnd,
    this.eventLocation,
    this.eventUrl,
  });

  bool get hasImage => image.isNotEmpty;

  factory Post.fromJson(Map<String, dynamic> json) {
    final cats = (json['categories'] as List<dynamic>? ?? [])
        .map((c) => PostCategory.fromJson(c as Map<String, dynamic>))
        .toList();
    return Post(
      id: json['id'] as int? ?? 0,
      type: json['type'] as String? ?? 'post',
      title: json['title'] as String? ?? '',
      excerpt: json['excerpt'] as String? ?? '',
      content: json['content'] as String? ?? '',
      date: DateTime.tryParse(json['date'] as String? ?? ''),
      link: json['link'] as String? ?? '',
      sourceUrl: json['source_url'] as String? ?? '',
      image: json['image'] as String? ?? '',
      author: json['author'] as String? ?? '',
      paid: json['paid'] == true || json['paid'] == 1,
      categories: cats,
      audioUrl: _str(json['audio_url']),
      duration: _str(json['duration']),
      videoId: _str(json['video_id']),
      eventStart: _str(json['event_start']),
      eventEnd: _str(json['event_end']),
      eventLocation: _str(json['event_location']),
      eventUrl: _str(json['event_url']),
    );
  }

  /// Flattened row for the sqflite offline cache.
  Map<String, dynamic> toCacheRow() => {
        'id': id,
        'type': type,
        'title': title,
        'excerpt': excerpt,
        'content': content,
        'date': date?.toIso8601String() ?? '',
        'link': link,
        'source_url': sourceUrl,
        'image': image,
        'author': author,
        'paid': paid ? 1 : 0,
        'categories': categories.map((c) => '${c.id}:${c.name}').join('|'),
        'audio_url': audioUrl ?? '',
        'duration': duration ?? '',
        'video_id': videoId ?? '',
        'event_start': eventStart ?? '',
        'event_end': eventEnd ?? '',
        'event_location': eventLocation ?? '',
        'event_url': eventUrl ?? '',
      };

  factory Post.fromCacheRow(Map<String, dynamic> row) {
    final cats = (row['categories'] as String? ?? '')
        .split('|')
        .where((s) => s.contains(':'))
        .map((s) {
      final parts = s.split(':');
      return PostCategory(
        id: int.tryParse(parts.first) ?? 0,
        name: parts.sublist(1).join(':'),
      );
    }).toList();
    return Post(
      id: row['id'] as int,
      type: row['type'] as String? ?? 'post',
      title: row['title'] as String? ?? '',
      excerpt: row['excerpt'] as String? ?? '',
      content: row['content'] as String? ?? '',
      date: DateTime.tryParse(row['date'] as String? ?? ''),
      link: row['link'] as String? ?? '',
      sourceUrl: row['source_url'] as String? ?? '',
      image: row['image'] as String? ?? '',
      author: row['author'] as String? ?? '',
      paid: (row['paid'] as int? ?? 0) == 1,
      categories: cats,
      audioUrl: _blankToNull(row['audio_url']),
      duration: _blankToNull(row['duration']),
      videoId: _blankToNull(row['video_id']),
      eventStart: _blankToNull(row['event_start']),
      eventEnd: _blankToNull(row['event_end']),
      eventLocation: _blankToNull(row['event_location']),
      eventUrl: _blankToNull(row['event_url']),
    );
  }

  static String? _str(dynamic v) {
    if (v == null) return null;
    final s = v.toString();
    return s.isEmpty ? null : s;
  }

  static String? _blankToNull(dynamic v) {
    final s = (v ?? '').toString();
    return s.isEmpty ? null : s;
  }
}

/// A category term attached to a post.
class PostCategory {
  final int id;
  final String name;
  final int count;

  const PostCategory({required this.id, required this.name, this.count = 0});

  factory PostCategory.fromJson(Map<String, dynamic> json) => PostCategory(
        id: json['id'] as int? ?? 0,
        name: json['name'] as String? ?? '',
        count: json['count'] as int? ?? 0,
      );
}
