/// The prx3/v1 API client: JWT auth with refresh, typed calls for every
/// member capability (FO-301, FO-302).
library;

import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

class Prx3ApiException implements Exception {
  Prx3ApiException(this.message, [this.statusCode]);
  final String message;
  final int? statusCode;
  @override
  String toString() => message;
}

class Prx3Api {
  Prx3Api(this.baseUrl);

  /// e.g. https://perthpanthers.example/wp-json/prx3/v1
  final String baseUrl;
  static const _storage = FlutterSecureStorage();

  Future<String?> get _accessToken => _storage.read(key: 'prx3_access');

  Future<bool> get signedIn async => await _accessToken != null;

  Future<void> login(String username, String password) async {
    try {
      final data = await _post('auth/login', {
        'username': username,
        'password': password,
      }, auth: false);
      await _storeTokens(data);
    } on Prx3ApiException catch (e) {
      // Some clubs enforce two-factor / block password-only REST logins.
      // In that case the same field may hold a WordPress Application
      // Password — transparently try that path before giving up.
      if (e.statusCode == 401 || e.statusCode == 403) {
        final data = await _post('auth/app-login', {
          'username': username,
          'app_password': password,
        }, auth: false);
        await _storeTokens(data);
      } else {
        rethrow;
      }
    }
  }

  Future<void> logout() async {
    await _storage.delete(key: 'prx3_access');
    await _storage.delete(key: 'prx3_refresh');
  }

  Future<void> _storeTokens(Map<String, dynamic> data) async {
    await _storage.write(key: 'prx3_access', value: data['access_token'] as String);
    await _storage.write(key: 'prx3_refresh', value: data['refresh_token'] as String);
  }

  Future<bool> _refresh() async {
    final refresh = await _storage.read(key: 'prx3_refresh');
    if (refresh == null) return false;
    try {
      final data = await _post('auth/refresh', {'refresh_token': refresh}, auth: false);
      await _storeTokens(data);
      return true;
    } on Prx3ApiException {
      await logout(); // Revoked session ends app access (FO-301 AC3).
      return false;
    }
  }

  Future<dynamic> _request(String method, String path,
      [Map<String, dynamic>? body, bool auth = true, bool retried = false]) async {
    final headers = <String, String>{'Content-Type': 'application/json'};
    if (auth) {
      final token = await _accessToken;
      if (token != null) headers['Authorization'] = 'Bearer $token';
    }
    final uri = Uri.parse('$baseUrl/$path');
    const timeout = Duration(seconds: 20);
    final http.Response response;
    try {
      response = await (method == 'GET'
              ? http.get(uri, headers: headers)
              : http.post(uri, headers: headers, body: jsonEncode(body ?? {})))
          .timeout(timeout);
    } on TimeoutException {
      throw Prx3ApiException(
          'The server took too long to respond. Check your connection and that the club site is reachable, then try again.');
    } on SocketException {
      throw Prx3ApiException(
          'Could not reach the club site. Check your connection and the site address, then try again.');
    } on http.ClientException {
      throw Prx3ApiException(
          'Could not reach the club site. Check your connection and the site address, then try again.');
    }
    final dynamic decoded;
    try {
      decoded = response.body.isEmpty ? {} : jsonDecode(response.body);
    } on FormatException {
      // A non-JSON body (e.g. an HTML error/login page from a security
      // plugin or a WAF) means we did not reach the REST API cleanly.
      throw Prx3ApiException(
          'The club site did not return app data (status ${response.statusCode}). The plugin may not be active, permalinks may need saving, or a security plugin is blocking the API.',
          response.statusCode);
    }
    if (response.statusCode == 401 && auth && !retried && await _refresh()) {
      return _request(method, path, body, auth, true);
    }
    if (response.statusCode >= 400) {
      throw Prx3ApiException(
        decoded is Map && decoded['message'] != null
            ? decoded['message'] as String
            : 'Something went wrong. Please try again.',
        response.statusCode,
      );
    }
    return decoded;
  }

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body,
          {bool auth = true}) async =>
      (await _request('POST', path, body, auth)) as Map<String, dynamic>;

  Future<dynamic> _get(String path) => _request('GET', path);

  // ---- Member capabilities (FO-302 parity) ----
  Future<Map<String, dynamic>> me() async => (await _get('me')) as Map<String, dynamic>;

  Future<void> registerPushToken(String token) =>
      _post('me/push-token', {'token': token});

  Future<List<dynamic>> ballots() async => (await _get('ballots')) as List<dynamic>;

  Future<Map<String, dynamic>> vote(int ballotId, int choice) =>
      _post('ballots/$ballotId/vote', {'choice': choice});

  Future<List<dynamic>> ideas() async => (await _get('ideas')) as List<dynamic>;

  Future<Map<String, dynamic>> submitIdea(String title, String body) =>
      _post('ideas', {'title': title, 'body': body});

  Future<Map<String, dynamic>> supportIdea(int id) => _post('ideas/$id/support', {});

  Future<List<dynamic>> questions() async => (await _get('questions')) as List<dynamic>;

  Future<Map<String, dynamic>> submitQuestion(String title) =>
      _post('questions', {'title': title, 'body': ''});

  Future<Map<String, dynamic>> upvoteQuestion(int id) =>
      _post('questions/$id/upvote', {});

  Future<List<dynamic>> meetings() async => (await _get('meetings')) as List<dynamic>;

  Future<Map<String, dynamic>> rsvp(int meetingId) =>
      _post('meetings/$meetingId/rsvp', {});

  Future<Map<String, dynamic>> match(int matchId) async =>
      (await _get('matches/$matchId')) as Map<String, dynamic>;

  Future<List<dynamic>> chat(String room, int sinceId) async =>
      (await _get('chat/$room?since=$sinceId')) as List<dynamic>;

  Future<Map<String, dynamic>> sendChat(String room, String body) =>
      _post('chat/$room', {'body': body});

  Future<Map<String, dynamic>> reportMatchEvent(int matchId, String clientKey,
          String eventType, int? minute, Map<String, dynamic> detail) =>
      _post('matches/$matchId/events', {
        'client_key': clientKey,
        'event_type': eventType,
        'minute': minute,
        'detail': detail,
      });

  Future<List<dynamic>> videos({String? type, String? search}) async =>
      (await _get('videos?type=${type ?? ''}&search=${search ?? ''}'))
          as List<dynamic>;

  Future<void> savePosition(int videoId, int seconds) =>
      _post('videos/$videoId/position', {'seconds': seconds});

  Future<List<dynamic>> decisions() async => (await _get('decisions')) as List<dynamic>;
}
