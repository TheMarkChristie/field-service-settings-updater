import 'dart:async';

import 'package:flutter/material.dart';

import '../api/fop_api.dart';

/// Match Centre (FO-305/306): live state, timeline, chat via the polling
/// transport (websocket transport swaps in per B4). The video player
/// itself embeds Cloudflare Stream via a webview/player package at
/// build-out; the signed playback token is already served.
class MatchScreen extends StatefulWidget {
  const MatchScreen({super.key, required this.api, this.matchId});
  final FopApi api;
  final int? matchId;

  @override
  State<MatchScreen> createState() => _MatchScreenState();
}

class _MatchScreenState extends State<MatchScreen> {
  Map<String, dynamic>? _match;
  final List<dynamic> _chat = [];
  int _lastChatId = 0;
  Timer? _matchTimer;
  Timer? _chatTimer;
  final _chatInput = TextEditingController();

  int get _matchId => widget.matchId ?? 0;

  @override
  void initState() {
    super.initState();
    if (_matchId > 0) {
      _poll();
      _matchTimer = Timer.periodic(const Duration(seconds: 20), (_) => _poll());
      _chatTimer = Timer.periodic(const Duration(seconds: 4), (_) => _pollChat());
    }
  }

  @override
  void dispose() {
    _matchTimer?.cancel();
    _chatTimer?.cancel();
    super.dispose();
  }

  Future<void> _poll() async {
    try {
      final match = await widget.api.match(_matchId);
      if (mounted) setState(() => _match = match);
    } on FopApiException {
      // Connectivity state surfaces through the UI's last-known state (T38).
    }
  }

  Future<void> _pollChat() async {
    try {
      final rows = await widget.api.chat('match-$_matchId', _lastChatId);
      if (rows.isEmpty || !mounted) return;
      setState(() {
        for (final row in rows) {
          _lastChatId = row['id'] as int;
          _chat.add(row);
        }
      });
    } on FopApiException {
      // Silent retry on next tick.
    }
  }

  Future<void> _send() async {
    final text = _chatInput.text.trim();
    if (text.isEmpty) return;
    try {
      await widget.api.sendChat('match-$_matchId', text);
      _chatInput.clear();
      await _pollChat();
    } on FopApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_matchId == 0) {
      return const Center(
          child: Text('No live match right now.\nMatch pages open from push notifications and fixtures.'));
    }
    final match = _match;
    if (match == null) {
      return const Center(child: CircularProgressIndicator());
    }
    final stateLabels = {
      'countdown': 'Kick-off ${match['kickoff'] ?? ''}',
      'live': 'LIVE ${match['score'] ?? ''}',
      'delayed': 'Kick-off due — stream on its way',
      'ended': 'Full-time ${match['score'] ?? ''}',
    };
    return Column(
      children: [
        ListTile(
          title: Text(match['title'] as String? ?? ''),
          subtitle: Text(stateLabels[match['state']] ?? ''),
        ),
        Expanded(
          child: ListView(
            children: [
              for (final entry in (match['timeline'] as List? ?? []))
                ListTile(
                  dense: true,
                  leading: Text("${entry['minute'] ?? ''}'"),
                  title: Text('${entry['event_type']}'.replaceAll('_', ' ')),
                ),
              const Divider(),
              for (final row in _chat)
                ListTile(dense: true, title: Text('${row['author']}: ${row['body']}')),
            ],
          ),
        ),
        SafeArea(
          child: Row(
            children: [
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 8),
                  child: TextField(
                    controller: _chatInput,
                    maxLength: 500,
                    decoration: const InputDecoration(
                        labelText: 'Match chat', counterText: ''),
                    onSubmitted: (_) => _send(),
                  ),
                ),
              ),
              IconButton(
                  onPressed: _send,
                  icon: const Icon(Icons.send),
                  tooltip: 'Send message'),
            ],
          ),
        ),
      ],
    );
  }
}
