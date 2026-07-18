/// Panthers Owners App — skeleton (B2 decision: API-complete plugin,
/// core screens wired, remaining screens structured for build-out).
///
/// Configure [apiBase] per environment; theming reads the club identity
/// from /me so a rebrand ballot restyles the app without a release (T46).
library;

import 'package:flutter/material.dart';

import 'api/prx3_api.dart';
import 'screens/ballots_screen.dart';
import 'screens/dashboard_screen.dart';
import 'screens/login_screen.dart';
import 'screens/match_screen.dart';
import 'screens/videos_screen.dart';

const String apiBase =
    String.fromEnvironment('PRX3_API', defaultValue: 'https://example.test/wp-json/prx3/v1');

void main() {
  // Firebase + Sentry initialisation land here when the project files
  // (google-services.json / GoogleService-Info.plist, DSN) are added.
  runApp(PanthersApp(api: Prx3Api(apiBase)));
}

class PanthersApp extends StatefulWidget {
  const PanthersApp({super.key, required this.api});
  final Prx3Api api;

  @override
  State<PanthersApp> createState() => _PanthersAppState();
}

class _PanthersAppState extends State<PanthersApp> {
  Map<String, dynamic>? _me;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (await widget.api.signedIn) {
      try {
        _me = await widget.api.me();
      } on Prx3ApiException {
        _me = null;
      }
    }
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final primary = _clubColor('primary') ?? const Color(0xFF1A1A2E);
    return MaterialApp(
      title: (_me?['club']?['name'] as String?) ?? 'Fan Owners',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: primary),
        useMaterial3: true,
      ),
      home: _checking
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _me == null
              ? LoginScreen(api: widget.api, onSignedIn: _bootstrap)
              : HomeShell(api: widget.api, me: _me!),
    );
  }

  Color? _clubColor(String key) {
    final hex = _me?['club']?[key] as String?;
    if (hex == null || !hex.startsWith('#') || hex.length != 7) return null;
    return Color(int.parse('FF${hex.substring(1)}', radix: 16));
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell({super.key, required this.api, required this.me});
  final Prx3Api api;
  final Map<String, dynamic> me;

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final screens = [
      DashboardScreen(api: widget.api, me: widget.me),
      BallotsScreen(api: widget.api),
      MatchScreen(api: widget.api),
      VideosScreen(api: widget.api),
    ];
    return Scaffold(
      body: SafeArea(child: screens[_index]),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.home_outlined), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.how_to_vote_outlined), label: 'Boardroom'),
          NavigationDestination(icon: Icon(Icons.sports_soccer_outlined), label: 'Match'),
          NavigationDestination(icon: Icon(Icons.play_circle_outline), label: 'TV'),
        ],
      ),
    );
  }
}
