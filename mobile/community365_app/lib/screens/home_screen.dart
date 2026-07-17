import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config.dart';
import '../services/auth_service.dart';
import 'categories_screen.dart';
import 'feed_tab.dart';
import 'login_screen.dart';
import 'settings_screen.dart';

/// The shell: four content tabs plus account/categories/settings actions.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _index = 0;

  late final List<Widget> _tabs = Config.contentTypes
      .map((t) => FeedTab(key: PageStorageKey(t.key), type: t.key))
      .toList();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final type = Config.contentTypes[_index];

    return Scaffold(
      appBar: AppBar(
        title: Row(
          children: [
            const Text('365'),
            Text(' Community',
                style: TextStyle(color: Theme.of(context).colorScheme.primary)),
          ],
        ),
        actions: [
          if (auth.isLoggedIn)
            IconButton(
              tooltip: 'My categories',
              icon: const Icon(Icons.tune),
              onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                  builder: (_) => const CategoriesScreen())),
            ),
          IconButton(
            tooltip: auth.isLoggedIn ? 'Account' : 'Sign in',
            icon: Icon(auth.isLoggedIn ? Icons.person : Icons.login),
            onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const LoginScreen())),
          ),
          IconButton(
            tooltip: 'Settings',
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const SettingsScreen())),
          ),
        ],
      ),
      body: IndexedStack(index: _index, children: _tabs),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: [
          for (final t in Config.contentTypes)
            NavigationDestination(
              icon: Icon(IconData(t.icon, fontFamily: 'MaterialIcons')),
              label: t.label,
            ),
        ],
      ),
      floatingActionButton: type.key == 'post'
          ? null
          : null, // reserved for future compose/quick actions
    );
  }
}
