import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/feed_repository.dart';
import '../state/feed_controller.dart';
import '../theme.dart';
import '../widgets/post_card.dart';
import 'article_screen.dart';

/// One content-type tab (blogs / events / podcasts / videos).
class FeedTab extends StatelessWidget {
  final String type;
  const FeedTab({super.key, required this.type});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (ctx) => FeedController(
        repo: ctx.read<FeedRepository>(),
        type: type,
      )..loadInitial(),
      child: const _FeedTabBody(),
    );
  }
}

class _FeedTabBody extends StatefulWidget {
  const _FeedTabBody();

  @override
  State<_FeedTabBody> createState() => _FeedTabBodyState();
}

class _FeedTabBodyState extends State<_FeedTabBody> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(() {
      if (_scroll.position.pixels >=
          _scroll.position.maxScrollExtent - 400) {
        context.read<FeedController>().loadMore();
      }
    });
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = context.watch<FeedController>();
    final colors = AppColors.of(context);

    if (controller.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.error != null) {
      return _Message(
        icon: Icons.cloud_off,
        title: 'Something went wrong',
        detail: controller.error!,
        onRetry: controller.refresh,
      );
    }
    if (controller.isEmpty) {
      return _Message(
        icon: Icons.inbox_outlined,
        title: 'Nothing here yet',
        detail: 'Check back soon for new content.',
        onRetry: controller.refresh,
      );
    }

    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView.builder(
        controller: _scroll,
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(vertical: 8),
        itemCount: controller.items.length +
            (controller.fromCache ? 1 : 0) +
            (controller.loadingMore ? 1 : 0),
        itemBuilder: (context, index) {
          if (controller.fromCache && index == 0) {
            return Container(
              margin: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: colors.surface,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: colors.border),
              ),
              child: Row(
                children: [
                  const Icon(Icons.offline_bolt, size: 18,
                      color: AppTheme.orange),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Offline — showing your downloaded content.',
                      style: TextStyle(color: colors.textSoft, fontSize: 13),
                    ),
                  ),
                ],
              ),
            );
          }
          final offset = controller.fromCache ? 1 : 0;
          final i = index - offset;
          if (i >= controller.items.length) {
            return const Padding(
              padding: EdgeInsets.all(16),
              child: Center(child: CircularProgressIndicator()),
            );
          }
          final post = controller.items[i];
          return PostCard(
            post: post,
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => ArticleScreen(post: post)),
            ),
          );
        },
      ),
    );
  }
}

class _Message extends StatelessWidget {
  final IconData icon;
  final String title;
  final String detail;
  final Future<void> Function() onRetry;

  const _Message({
    required this.icon,
    required this.title,
    required this.detail,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: colors.textSoft),
            const SizedBox(height: 12),
            Text(title,
                style: const TextStyle(
                    fontSize: 18, fontWeight: FontWeight.w800)),
            const SizedBox(height: 6),
            Text(detail,
                textAlign: TextAlign.center,
                style: TextStyle(color: colors.textSoft)),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh),
              label: const Text('Try again'),
            ),
          ],
        ),
      ),
    );
  }
}
