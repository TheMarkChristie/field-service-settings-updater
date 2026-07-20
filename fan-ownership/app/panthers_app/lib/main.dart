/// Panthers Owners App — skeleton (B2 decision: API-complete plugin,
/// core screens wired, remaining screens structured for build-out).
///
/// Configure [apiBase] per environment; theming reads the club identity
/// from /me so a rebrand ballot restyles the app without a release (T46).
library;

import 'package:flutter/material.dart';

import 'api/prx3_api.dart';
import 'brand.dart';
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
  ClubBrand? _brand;
  bool _checking = true;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    Map<String, dynamic>? club;
    if (await widget.api.signedIn) {
      try {
        _me = await widget.api.me();
        club = _me?['club'] as Map<String, dynamic>?;
      } catch (_) {
        // Token present but the profile could not be loaded: drop the
        // session so the user lands back on a usable sign-in screen.
        _me = null;
        await widget.api.logout();
      }
    }
    // Signed out (or profile load failed): brand the sign-in screen from
    // the public club config so it still carries the club's identity.
    if (club == null) {
      try {
        club = (await widget.api.config())['club'] as Map<String, dynamic>?;
      } catch (_) {
        club = null;
      }
    }
    if (club != null) {
      final family =
          await loadClubFont(club['font_file'] as String?, club['font_name'] as String?);
      _brand = ClubBrand(club, fontFamily: family);
    }
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final brand = _brand;
    return MaterialApp(
      title: brand?.name ?? 'Fan Owners',
      theme: (brand ?? ClubBrand(const {})).theme(Brightness.light),
      darkTheme: (brand ?? ClubBrand(const {})).theme(Brightness.dark),
      themeMode: ThemeMode.system,
      home: _checking
          ? const Scaffold(body: Center(child: CircularProgressIndicator()))
          : _me == null
              ? LoginScreen(api: widget.api, onSignedIn: _bootstrap, brand: brand)
              : HomeShell(api: widget.api, me: _me!, brand: brand!),
    );
  }
}

class HomeShell extends StatefulWidget {
  const HomeShell(
      {super.key, required this.api, required this.me, required this.brand});
  final Prx3Api api;
  final Map<String, dynamic> me;
  final ClubBrand brand;

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
    final badge = widget.brand.badge;
    return Scaffold(
      appBar: AppBar(
        titleSpacing: badge != null ? 8 : null,
        leading: badge != null
            ? Padding(
                padding: const EdgeInsets.all(8),
                child: Image.network(badge,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink()),
              )
            : null,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(widget.brand.name,
                style: const TextStyle(fontWeight: FontWeight.bold)),
            if (widget.brand.tagline != null)
              Text(widget.brand.tagline!,
                  style: const TextStyle(fontSize: 11),
                  overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
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
