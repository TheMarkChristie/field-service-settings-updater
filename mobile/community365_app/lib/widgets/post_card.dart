import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/post.dart';
import '../theme.dart';

/// A feed card: image with an optional "Paid content" badge, kind/date
/// meta, title, excerpt, and author.
class PostCard extends StatelessWidget {
  final Post post;
  final VoidCallback onTap;

  const PostCard({super.key, required this.post, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    return Card(
      child: InkWell(
        onTap: onTap,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (post.hasImage)
              Stack(
                children: [
                  AspectRatio(
                    aspectRatio: 16 / 9,
                    child: CachedNetworkImage(
                      imageUrl: post.image,
                      fit: BoxFit.cover,
                      placeholder: (_, __) => Container(color: colors.surface),
                      errorWidget: (_, __, ___) =>
                          Container(color: colors.surface),
                    ),
                  ),
                  if (post.paid)
                    Positioned(
                      top: 10,
                      left: 10,
                      child: _PaidBadge(),
                    ),
                ],
              ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        _kind(post.type).toUpperCase(),
                        style: TextStyle(
                          color: AppTheme.orange,
                          fontWeight: FontWeight.w800,
                          fontSize: 11,
                          letterSpacing: 0.6,
                        ),
                      ),
                      if (post.date != null) ...[
                        Text('  ·  ',
                            style: TextStyle(color: colors.textSoft)),
                        Text(
                          DateFormat.yMMMd().format(post.date!),
                          style: TextStyle(
                              color: colors.textSoft, fontSize: 12),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    post.title,
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      height: 1.3,
                    ),
                  ),
                  if (post.excerpt.isNotEmpty) ...[
                    const SizedBox(height: 6),
                    Text(
                      post.excerpt,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: colors.textSoft, height: 1.4),
                    ),
                  ],
                  if (post.author.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      'by ${post.author}',
                      style: TextStyle(
                        color: colors.textSoft,
                        fontSize: 12,
                        fontStyle: FontStyle.italic,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
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
        return 'Blog';
    }
  }
}

class _PaidBadge extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: 0.85),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: AppTheme.orange),
      ),
      child: const Text(
        'PAID CONTENT',
        style: TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.6,
        ),
      ),
    );
  }
}
