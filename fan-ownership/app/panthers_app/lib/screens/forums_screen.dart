import 'package:flutter/material.dart';

import '../api/prx3_api.dart';

/// FO-320: owner discussion forums — browse threads, read, and reply.
class ForumsScreen extends StatefulWidget {
  const ForumsScreen({super.key, required this.api});
  final Prx3Api api;

  @override
  State<ForumsScreen> createState() => _ForumsScreenState();
}

class _ForumsScreenState extends State<ForumsScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.forumTopics();
  }

  void _reload() => setState(() => _future = widget.api.forumTopics());

  Future<void> _newTopic() async {
    final created = await showDialog<bool>(
      context: context,
      builder: (_) => _NewTopicDialog(api: widget.api),
    );
    if (created == true) _reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Forums')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _newTopic,
        icon: const Icon(Icons.add),
        label: const Text('New topic'),
      ),
      body: RefreshIndicator(
        onRefresh: () async => _reload(),
        child: FutureBuilder<List<dynamic>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return ListView(children: [
                Padding(
                  padding: const EdgeInsets.all(24),
                  child: Text('${snap.error}', textAlign: TextAlign.center),
                )
              ]);
            }
            final topics = (snap.data ?? const []).cast<Map<String, dynamic>>();
            if (topics.isEmpty) {
              return ListView(children: const [
                Padding(
                  padding: EdgeInsets.all(24),
                  child: Text('No topics yet. Start one!',
                      textAlign: TextAlign.center),
                )
              ]);
            }
            return ListView(
              children: [
                for (final t in topics)
                  ListTile(
                    leading: const Icon(Icons.chat_bubble_outline),
                    title: Text(t['title'] as String? ?? 'Topic'),
                    subtitle: Text('${t['replies'] ?? 0} replies'),
                    trailing: (t['unread'] ?? 0) is int && (t['unread'] ?? 0) > 0
                        ? Badge(label: Text('${t['unread']}'))
                        : null,
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => ThreadScreen(
                        api: widget.api,
                        topicId: t['id'] as int,
                        title: t['title'] as String? ?? 'Topic',
                      ),
                    )),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}

/// A single thread with its replies and a reply box.
class ThreadScreen extends StatefulWidget {
  const ThreadScreen(
      {super.key, required this.api, required this.topicId, required this.title});
  final Prx3Api api;
  final int topicId;
  final String title;

  @override
  State<ThreadScreen> createState() => _ThreadScreenState();
}

class _ThreadScreenState extends State<ThreadScreen> {
  late Future<List<dynamic>> _future;
  final _reply = TextEditingController();
  bool _sending = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _future = widget.api.forumReplies(widget.topicId);
  }

  Future<void> _send() async {
    if (_reply.text.trim().isEmpty) return;
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await widget.api.replyToTopic(widget.topicId, _reply.text.trim());
      _reply.clear();
      setState(() => _future = widget.api.forumReplies(widget.topicId));
    } on Prx3ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: Column(
        children: [
          Expanded(
            child: FutureBuilder<List<dynamic>>(
              future: _future,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snap.hasError) {
                  return Center(
                      child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text('${snap.error}', textAlign: TextAlign.center),
                  ));
                }
                final replies =
                    (snap.data ?? const []).cast<Map<String, dynamic>>();
                if (replies.isEmpty) {
                  return const Center(child: Text('No replies yet.'));
                }
                return ListView(
                  padding: const EdgeInsets.all(8),
                  children: [
                    for (final r in replies)
                      Card(
                        child: ListTile(
                          title: Text(r['author'] as String? ?? 'Owner'),
                          subtitle: Text(r['body'] as String? ?? ''),
                        ),
                      ),
                  ],
                );
              },
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _reply,
                      minLines: 1,
                      maxLines: 4,
                      decoration: const InputDecoration(
                        hintText: 'Write a reply…',
                        border: OutlineInputBorder(),
                        isDense: true,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    onPressed: _sending ? null : _send,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NewTopicDialog extends StatefulWidget {
  const _NewTopicDialog({required this.api});
  final Prx3Api api;

  @override
  State<_NewTopicDialog> createState() => _NewTopicDialogState();
}

class _NewTopicDialogState extends State<_NewTopicDialog> {
  final _title = TextEditingController();
  final _body = TextEditingController();
  bool _saving = false;
  String? _error;

  Future<void> _create() async {
    if (_title.text.trim().isEmpty || _body.text.trim().isEmpty) {
      setState(() => _error = 'A title and a post are both required.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.createTopic(_title.text.trim(), _body.text.trim());
      if (mounted) Navigator.of(context).pop(true);
    } on Prx3ApiException catch (e) {
      setState(() {
        _error = e.message;
        _saving = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('New topic'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            controller: _title,
            decoration: const InputDecoration(labelText: 'Title'),
          ),
          TextField(
            controller: _body,
            minLines: 2,
            maxLines: 5,
            decoration: const InputDecoration(labelText: 'Your post'),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.of(context).pop(false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving ? null : _create,
          child: Text(_saving ? 'Posting…' : 'Post'),
        ),
      ],
    );
  }
}
