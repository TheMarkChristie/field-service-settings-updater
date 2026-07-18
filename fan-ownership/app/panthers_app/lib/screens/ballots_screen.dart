import 'package:flutter/material.dart';

import '../api/fop_api.dart';

/// FO-202/FO-203 in the app: weighted secret voting, change until close.
class BallotsScreen extends StatefulWidget {
  const BallotsScreen({super.key, required this.api});
  final FopApi api;

  @override
  State<BallotsScreen> createState() => _BallotsScreenState();
}

class _BallotsScreenState extends State<BallotsScreen> {
  late Future<List<dynamic>> _ballots;

  @override
  void initState() {
    super.initState();
    _ballots = widget.api.ballots();
  }

  Future<void> _vote(int ballotId, int choice) async {
    try {
      final result = await widget.api.vote(ballotId, choice);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(result['revised'] == true
            ? 'Vote updated — only your final choice counts.'
            : 'Vote recorded: ${result['weight']} vote(s). Results appear when the ballot closes.'),
      ));
      setState(() => _ballots = widget.api.ballots());
    } on FopApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: () async => setState(() => _ballots = widget.api.ballots()),
      child: FutureBuilder<List<dynamic>>(
        future: _ballots,
        builder: (context, snapshot) {
          if (!snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          final ballots = snapshot.data!;
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: ballots.length,
            itemBuilder: (context, i) {
              final ballot = ballots[i] as Map<String, dynamic>;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(ballot['title'] as String? ?? '',
                          style: Theme.of(context).textTheme.titleMedium),
                      if (ballot['type'] == 'constitutional')
                        const Chip(label: Text('Constitutional — 75% to pass')),
                      if ((ballot['board_recommendation'] as String?)?.isNotEmpty ?? false)
                        Text('Board recommendation: ${ballot['board_recommendation']}'),
                      const SizedBox(height: 8),
                      if (ballot['state'] == 'open')
                        ...List<Widget>.generate(
                          (ballot['options'] as List).length,
                          (o) => RadioListTile<int>(
                            title: Text((ballot['options'] as List)[o] as String),
                            value: o,
                            groupValue: ballot['my_choice'] as int?,
                            onChanged: (v) => _vote(ballot['id'] as int, v!),
                          ),
                        )
                      else if (ballot['result'] is Map &&
                          (ballot['result']['outcome'] == 'passed'))
                        Text(
                            'Result: ${(ballot['options'] as List)[ballot['result']['winning'] as int]}')
                      else if (ballot['secret'] == true)
                        const Text('Secret ballot — results appear at close.'),
                      Text('State: ${ballot['state']} · closes ${ballot['closes'] ?? ''}'),
                    ],
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
