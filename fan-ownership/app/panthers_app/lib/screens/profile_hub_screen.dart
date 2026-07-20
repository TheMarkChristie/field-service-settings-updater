import 'package:flutter/material.dart';

import '../api/prx3_api.dart';
import 'documents_screen.dart';
import 'forums_screen.dart';
import 'identity_screen.dart';

/// FO-320: the owner's account hub — details, KYC, documents, forums.
class ProfileHubScreen extends StatelessWidget {
  const ProfileHubScreen({super.key, required this.api, required this.me});
  final Prx3Api api;
  final Map<String, dynamic> me;

  @override
  Widget build(BuildContext context) {
    final owner = me['owner_number']?.toString();
    final shares = me['shares']?.toString() ?? '0';
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 28,
                  child: Text(
                    (me['name'] as String? ?? '?').characters.first.toUpperCase(),
                    style: const TextStyle(fontSize: 22),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(me['name'] as String? ?? 'Owner',
                          style: Theme.of(context).textTheme.titleLarge),
                      if (owner != null && owner.isNotEmpty)
                        Text('Owner #$owner'),
                      Text('$shares share${shares == '1' ? '' : 's'}'),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 8),
        _tile(context, Icons.person_outline, 'My profile',
            'Bio, links and preferences', EditProfileScreen(api: api)),
        _tile(context, Icons.verified_user_outlined, 'Identity & KYC',
            'Your private identity record', IdentityScreen(api: api)),
        _tile(context, Icons.folder_outlined, 'Documents',
            'Agreement, certificate and club papers', DocumentsScreen(api: api)),
        _tile(context, Icons.forum_outlined, 'Forums',
            'Owner discussion boards', ForumsScreen(api: api)),
      ],
    );
  }

  Widget _tile(BuildContext context, IconData icon, String title, String sub,
      Widget dest) {
    return Card(
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(sub),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => Navigator.of(context)
            .push(MaterialPageRoute(builder: (_) => dest)),
      ),
    );
  }
}

/// Edit the owner's own content fields (FO-320).
class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({super.key, required this.api});
  final Prx3Api api;

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _bio = TextEditingController();
  final _x = TextEditingController();
  final _instagram = TextEditingController();
  final _facebook = TextEditingController();
  final _bluesky = TextEditingController();
  bool _sharesPublic = false;
  bool _photoConsent = false;
  bool _notifyEmailOff = false;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final p = await widget.api.profile();
      final s = (p['socials'] as Map?) ?? {};
      _bio.text = p['bio'] as String? ?? '';
      _x.text = s['x'] as String? ?? '';
      _instagram.text = s['instagram'] as String? ?? '';
      _facebook.text = s['facebook'] as String? ?? '';
      _bluesky.text = s['bluesky'] as String? ?? '';
      _sharesPublic = p['shares_public'] == true;
      _photoConsent = p['photo_consent'] == true;
      _notifyEmailOff = p['notify_email_off'] == true;
    } catch (e) {
      _error = e.toString();
    }
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.api.saveProfile({
        'bio': _bio.text,
        'socials': {
          'x': _x.text.trim(),
          'instagram': _instagram.text.trim(),
          'facebook': _facebook.text.trim(),
          'bluesky': _bluesky.text.trim(),
        },
        'shares_public': _sharesPublic,
        'photo_consent': _photoConsent,
        'notify_email_off': _notifyEmailOff,
      });
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(const SnackBar(content: Text('Profile saved')));
        Navigator.of(context).pop();
      }
    } on Prx3ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My profile')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                  ),
                TextField(
                  controller: _bio,
                  maxLength: 300,
                  maxLines: 4,
                  decoration: const InputDecoration(
                      labelText: 'Bio', border: OutlineInputBorder()),
                ),
                const SizedBox(height: 8),
                _social(_x, 'X (Twitter)'),
                _social(_instagram, 'Instagram'),
                _social(_facebook, 'Facebook'),
                _social(_bluesky, 'Bluesky'),
                SwitchListTile(
                  value: _sharesPublic,
                  onChanged: (v) => setState(() => _sharesPublic = v),
                  title: const Text('Show my share count to fellow owners'),
                ),
                SwitchListTile(
                  value: _photoConsent,
                  onChanged: (v) => setState(() => _photoConsent = v),
                  title: const Text('Allow the club to use my gallery photos'),
                ),
                SwitchListTile(
                  value: _notifyEmailOff,
                  onChanged: (v) => setState(() => _notifyEmailOff = v),
                  title: const Text('Turn off email notifications'),
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save'),
                ),
              ],
            ),
    );
  }

  Widget _social(TextEditingController c, String label) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: TextField(
          controller: c,
          keyboardType: TextInputType.url,
          decoration: InputDecoration(
              labelText: label, hintText: 'https://…', isDense: true),
        ),
      );
}
