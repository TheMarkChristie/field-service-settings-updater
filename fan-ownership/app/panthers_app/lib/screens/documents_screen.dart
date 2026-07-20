import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../api/prx3_api.dart';

/// FO-320: the documents an owner can access — agreement, certificate,
/// and any published club documents. Each opens on the website.
class DocumentsScreen extends StatefulWidget {
  const DocumentsScreen({super.key, required this.api});
  final Prx3Api api;

  @override
  State<DocumentsScreen> createState() => _DocumentsScreenState();
}

class _DocumentsScreenState extends State<DocumentsScreen> {
  late Future<List<dynamic>> _future;

  @override
  void initState() {
    super.initState();
    _future = widget.api.documents();
  }

  IconData _icon(String type) {
    switch (type) {
      case 'agreement':
        return Icons.gavel_outlined;
      case 'certificate':
        return Icons.workspace_premium_outlined;
      default:
        return Icons.description_outlined;
    }
  }

  Future<void> _open(String? url) async {
    if (url == null || url.isEmpty) return;
    final uri = Uri.tryParse(url);
    if (uri != null) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Documents')),
      body: FutureBuilder<List<dynamic>>(
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
          final docs = snap.data ?? const [];
          if (docs.isEmpty) {
            return const Center(child: Text('No documents yet.'));
          }
          return ListView(
            children: [
              for (final d in docs.cast<Map<String, dynamic>>())
                Card(
                  margin:
                      const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  child: ListTile(
                    leading: Icon(_icon(d['type'] as String? ?? '')),
                    title: Text(d['title'] as String? ?? 'Document'),
                    subtitle: d['current'] == false
                        ? const Text('Action needed — please re-sign')
                        : null,
                    trailing: const Icon(Icons.open_in_new),
                    onTap: () => _open(
                        (d['executed_url'] ?? d['url']) as String?),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}
