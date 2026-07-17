import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../services/api_client.dart';
import '../services/auth_service.dart';
import '../theme.dart';

/// Sign in with a WordPress username + Application Password. When
/// already signed in, this screen shows the account and a sign-out
/// button.
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _user = TextEditingController();
  final _pass = TextEditingController();
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _user.dispose();
    _pass.dispose();
    super.dispose();
  }

  Future<void> _signIn() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final auth = context.read<AuthService>();
    final api = context.read<ApiClient>();
    // Store, then verify; roll back if the credentials are rejected.
    await auth.signIn(_user.text.trim(), _pass.text.trim());
    try {
      final ok = await api.verifyCredentials();
      if (!ok) {
        await auth.signOut();
        if (!mounted) return;
        setState(() =>
            _error = 'Those credentials weren’t accepted. Please try again.');
        return;
      }
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      await auth.signOut();
      if (!mounted) return;
      setState(() => _error = e.message);
    } catch (_) {
      await auth.signOut();
      if (!mounted) return;
      setState(() => _error = 'Couldn’t reach the server. Check your connection.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthService>();
    final colors = AppColors.of(context);

    if (auth.isLoggedIn) {
      return Scaffold(
        appBar: AppBar(title: const Text('Account')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.person, size: 64, color: AppTheme.orange),
                const SizedBox(height: 12),
                Text('Signed in as',
                    style: TextStyle(color: colors.textSoft)),
                Text(auth.username ?? '',
                    style: const TextStyle(
                        fontSize: 20, fontWeight: FontWeight.w800)),
                const SizedBox(height: 8),
                Text(
                  'You’re seeing content from the categories you follow. '
                  'Manage them from the sliders icon.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: colors.textSoft),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  onPressed: () async {
                    await auth.signOut();
                    if (context.mounted) Navigator.of(context).pop();
                  },
                  icon: const Icon(Icons.logout),
                  label: const Text('Sign out'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Sign in')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(
            'Sign in to see the categories you follow and download them for '
            'offline reading. Without signing in you’ll see the last 5 days '
            'of everything.',
            style: TextStyle(color: colors.textSoft, height: 1.5),
          ),
          const SizedBox(height: 24),
          TextField(
            controller: _user,
            autofillHints: const [AutofillHints.username],
            decoration: const InputDecoration(
              labelText: 'WordPress username',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _pass,
            obscureText: true,
            decoration: const InputDecoration(
              labelText: 'Application password',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: () => launchUrl(
              Uri.parse('${Config.siteUrl}/wp-admin/profile.php'),
              mode: LaunchMode.externalApplication,
            ),
            icon: const Icon(Icons.help_outline, size: 18),
            label: const Text('How do I get an application password?'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.redAccent)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: _busy ? null : _signIn,
            child: _busy
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: Colors.white),
                  )
                : const Text('Sign in'),
          ),
        ],
      ),
    );
  }
}
