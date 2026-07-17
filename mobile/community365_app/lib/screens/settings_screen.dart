import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config.dart';
import '../services/cache_service.dart';
import '../services/settings_service.dart';
import '../theme.dart';

/// Theme, offline download size, and cache management.
class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  int? _cachedCount;

  @override
  void initState() {
    super.initState();
    _refreshCount();
  }

  Future<void> _refreshCount() async {
    final n = await context.read<CacheService>().count();
    if (mounted) setState(() => _cachedCount = n);
  }

  @override
  Widget build(BuildContext context) {
    final settings = context.watch<SettingsService>();
    final cache = context.read<CacheService>();
    final colors = AppColors.of(context);

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: [
          _header('Appearance', colors),
          RadioListTile<ThemeMode>(
            value: ThemeMode.system,
            groupValue: settings.themeMode,
            activeColor: AppTheme.orange,
            title: const Text('Follow device'),
            onChanged: (m) => settings.setThemeMode(m!),
          ),
          RadioListTile<ThemeMode>(
            value: ThemeMode.light,
            groupValue: settings.themeMode,
            activeColor: AppTheme.orange,
            title: const Text('Light'),
            onChanged: (m) => settings.setThemeMode(m!),
          ),
          RadioListTile<ThemeMode>(
            value: ThemeMode.dark,
            groupValue: settings.themeMode,
            activeColor: AppTheme.orange,
            title: const Text('Dark'),
            onChanged: (m) => settings.setThemeMode(m!),
          ),
          const Divider(),
          _header('Offline reading', colors),
          SwitchListTile(
            value: settings.offlineEnabled,
            activeColor: AppTheme.orange,
            title: const Text('Keep content for offline reading'),
            subtitle: const Text(
                'Downloads the latest items so you can read without a signal.'),
            onChanged: (v) => settings.setOfflineEnabled(v),
          ),
          if (settings.offlineEnabled)
            ListTile(
              title: const Text('How many latest items to keep'),
              subtitle: Text('${settings.offlineLimit} per section'),
              trailing: DropdownButton<int>(
                value: settings.offlineLimit,
                items: [
                  for (final n in Config.offlineLimitChoices)
                    DropdownMenuItem(value: n, child: Text('$n')),
                ],
                onChanged: (n) async {
                  if (n == null) return;
                  await settings.setOfflineLimit(n);
                  await cache.trimAll(n);
                  _refreshCount();
                },
              ),
            ),
          ListTile(
            title: const Text('Downloaded items'),
            subtitle: Text(_cachedCount == null
                ? '…'
                : '$_cachedCount stored on this device'),
            trailing: TextButton(
              onPressed: () async {
                await cache.clear();
                _refreshCount();
                if (context.mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Offline content cleared.')),
                  );
                }
              },
              child: const Text('Clear'),
            ),
          ),
          const Divider(),
          _header('About', colors),
          const ListTile(
            title: Text('365 Community'),
            subtitle: Text('Version 1.0.0'),
          ),
        ],
      ),
    );
  }

  Widget _header(String text, AppColors colors) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 4),
        child: Text(
          text.toUpperCase(),
          style: TextStyle(
            color: AppTheme.orange,
            fontWeight: FontWeight.w800,
            fontSize: 12,
            letterSpacing: 0.8,
          ),
        ),
      );
}
