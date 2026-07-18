import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/prx3_api.dart';

/// Owner home: identity, shares, ladder link-out (T33), quick sections.
class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key, required this.api, required this.me});
  final Prx3Api api;
  final Map<String, dynamic> me;

  @override
  Widget build(BuildContext context) {
    final shares = me['shares'] as int? ?? 0;
    final maxShares = me['max_shares'] as int? ?? 10;
    final nextPrice = me['next_share_price'];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text('${me['club']?['name'] ?? ''}',
            style: Theme.of(context).textTheme.headlineSmall),
        Text('Welcome back, ${me['name']} — Owner #${me['owner_number']}'),
        const SizedBox(height: 16),
        Card(
          child: ListTile(
            title: Text('$shares of $maxShares shares'),
            subtitle: Text(
                'Each share is one vote on every ballot.\nTicket discounts: ${me['discounts']?['matchday']}% matchday, ${me['discounts']?['season']}% season ticket.'),
            trailing: shares < maxShares && nextPrice != null
                ? FilledButton(
                    // Share purchases go through the web checkout (T33).
                    onPressed: () => launchUrl(
                        Uri.parse(me['checkout_url'] as String),
                        mode: LaunchMode.externalApplication),
                    child: Text('Buy share\n£$nextPrice'),
                  )
                : null,
          ),
        ),
        Card(
          child: ListTile(
            leading: const Icon(Icons.badge_outlined),
            title: const Text('My badges'),
            subtitle: Text(((me['badges'] as List?) ?? []).join(', ')),
          ),
        ),
        Card(
          child: ListTile(
            leading: const Icon(Icons.calendar_month_outlined),
            title: const Text('Owners calendar'),
            subtitle: const Text('Meetings, matches and ballot deadlines'),
            onTap: () => launchUrl(Uri.parse(me['calendar_url'] as String),
                mode: LaunchMode.externalApplication),
          ),
        ),
        // Structured build-out stubs: ideas, questions, meetings, decisions,
        // forum — each already served by the API client (FO-302).
        const Card(
          child: ListTile(
            leading: Icon(Icons.construction_outlined),
            title: Text('Ideas · Questions · Meetings · Decision register'),
            subtitle: Text('Wired in the API client; screens land in the next app iteration.'),
          ),
        ),
      ],
    );
  }
}
