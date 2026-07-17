import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/post.dart';
import '../services/api_client.dart';
import '../services/push_service.dart';
import '../theme.dart';

/// The member's category preferences — which categories their feed and
/// downloads are drawn from. Saving also updates push topic subscriptions.
class CategoriesScreen extends StatefulWidget {
  const CategoriesScreen({super.key});

  @override
  State<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends State<CategoriesScreen> {
  List<PostCategory>? _categories;
  Set<int> _selected = {};
  Set<int> _original = {};
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final api = context.read<ApiClient>();
    try {
      final cats = await api.categories();
      final prefs = await api.getPreferences();
      if (!mounted) return;
      setState(() {
        _categories = cats;
        _selected = prefs.toSet();
        _original = prefs.toSet();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _error = 'Couldn’t load categories. Check your connection.';
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    final api = context.read<ApiClient>();
    final push = context.read<PushService>();
    try {
      await api.setPreferences(_selected.toList());
      // Sync push topics with the change set.
      for (final id in _selected.difference(_original)) {
        await push.setCategory(id, true);
      }
      for (final id in _original.difference(_selected)) {
        await push.setCategory(id, false);
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Your categories were saved.')),
        );
        Navigator.of(context).pop(true);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Couldn’t save — please try again.')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final colors = AppColors.of(context);
    final changed = !_selected.containsAll(_original) ||
        !_original.containsAll(_selected);

    return Scaffold(
      appBar: AppBar(title: const Text('My categories')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(_error!,
                        textAlign: TextAlign.center,
                        style: TextStyle(color: colors.textSoft)),
                  ),
                )
              : Column(
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(
                        'Choose the topics you want in your feed and offline '
                        'downloads. Leave all off to see everything.',
                        style: TextStyle(color: colors.textSoft),
                      ),
                    ),
                    Expanded(
                      child: ListView(
                        children: [
                          for (final cat in _categories ?? [])
                            CheckboxListTile(
                              value: _selected.contains(cat.id),
                              activeColor: AppTheme.orange,
                              title: Text(cat.name),
                              subtitle: cat.count > 0
                                  ? Text('${cat.count} posts')
                                  : null,
                              onChanged: (v) => setState(() {
                                if (v == true) {
                                  _selected.add(cat.id);
                                } else {
                                  _selected.remove(cat.id);
                                }
                              }),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
      bottomNavigationBar: (_error == null && !_loading)
          ? SafeArea(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: FilledButton(
                  onPressed: (!changed || _saving) ? null : _save,
                  child: _saving
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(
                              strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Save my categories'),
                ),
              ),
            )
          : null,
    );
  }
}
