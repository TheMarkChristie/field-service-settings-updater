import 'package:flutter/material.dart';

import '../api/prx3_api.dart';

/// FO-320 / P138: the owner views and edits their own KYC/identity record.
/// Every change is audited server-side.
class IdentityScreen extends StatefulWidget {
  const IdentityScreen({super.key, required this.api});
  final Prx3Api api;

  @override
  State<IdentityScreen> createState() => _IdentityScreenState();
}

class _IdentityScreenState extends State<IdentityScreen> {
  final _birthName = TextEditingController();
  final _nationality = TextEditingController();
  final _residence = TextEditingController();
  final _dob = TextEditingController();
  final _govId = TextEditingController();
  String _pep = '';
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
      final id = await widget.api.identity();
      _birthName.text = id['birth_name'] as String? ?? '';
      _nationality.text = id['nationality'] as String? ?? '';
      _residence.text = id['residence'] as String? ?? '';
      _dob.text = id['dob'] as String? ?? '';
      _govId.text = id['gov_id'] as String? ?? '';
      _pep = (id['pep'] as String? ?? '');
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
      await widget.api.saveIdentity({
        'birth_name': _birthName.text.trim(),
        'nationality': _nationality.text.trim(),
        'residence': _residence.text.trim(),
        'dob': _dob.text.trim(),
        'gov_id': _govId.text.trim(),
        'pep': _pep,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Identity record updated')));
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
      appBar: AppBar(title: const Text('Identity & KYC')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Card(
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                  child: const Padding(
                    padding: EdgeInsets.all(12),
                    child: Text(
                        'This is your private identity record, used for '
                        'compliance. Only you, and the board under audit, can '
                        'see it. Every change you make here is logged.'),
                  ),
                ),
                const SizedBox(height: 12),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(_error!,
                        style: TextStyle(
                            color: Theme.of(context).colorScheme.error)),
                  ),
                _field(_birthName, 'Full legal name'),
                _field(_nationality, 'Nationality'),
                _field(_residence, 'Country of residence'),
                _field(_dob, 'Date of birth', hint: 'YYYY-MM-DD'),
                _field(_govId, 'Government ID number'),
                const SizedBox(height: 8),
                DropdownButtonFormField<String>(
                  initialValue: _pep.isEmpty ? null : _pep,
                  decoration: const InputDecoration(
                      labelText: 'Politically Exposed Person (PEP)?'),
                  items: const [
                    DropdownMenuItem(value: 'no', child: Text('No')),
                    DropdownMenuItem(value: 'yes', child: Text('Yes')),
                  ],
                  onChanged: (v) => setState(() => _pep = v ?? ''),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save identity record'),
                ),
              ],
            ),
    );
  }

  Widget _field(TextEditingController c, String label, {String? hint}) =>
      Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: TextField(
          controller: c,
          decoration: InputDecoration(
              labelText: label, hintText: hint, border: const OutlineInputBorder()),
        ),
      );
}
