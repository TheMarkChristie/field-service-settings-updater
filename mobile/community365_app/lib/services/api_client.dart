import 'dart:convert';
import 'package:http/http.dart' as http;

import '../config.dart';
import '../models/post.dart';
import 'auth_service.dart';

/// One page of feed results.
class FeedPage {
  final List<Post> items;
  final int page;
  final int totalPages;
  final bool loggedIn;

  const FeedPage({
    required this.items,
    required this.page,
    required this.totalPages,
    required this.loggedIn,
  });

  bool get hasMore => page < totalPages;
}

/// Raised when the API returns an error or authentication fails.
class ApiException implements Exception {
  final String message;
  final int? status;
  ApiException(this.message, [this.status]);
  @override
  String toString() => message;
}

/// Thin client over the Syndicate Pro REST API (`synpro/v1`).
class ApiClient {
  final AuthService auth;
  final http.Client _http;

  ApiClient(this.auth, {http.Client? client}) : _http = client ?? http.Client();

  Future<Map<String, String>> _headers() async {
    final headers = {'Accept': 'application/json'};
    final authHeader = await auth.authHeader();
    if (authHeader != null) headers['Authorization'] = authHeader;
    return headers;
  }

  /// Fetch a page of a content type. Logged-in members get their chosen
  /// categories; anonymous users get the last 5 days of everything.
  Future<FeedPage> feed(String type, {int page = 1}) async {
    final uri = Uri.parse('${Config.apiBase}/feed').replace(queryParameters: {
      'type': type,
      'page': '$page',
      'per_page': '${Config.pageSize}',
    });
    final res = await _http.get(uri, headers: await _headers());
    final json = _decode(res);
    final items = (json['items'] as List<dynamic>? ?? [])
        .map((e) => Post.fromJson(e as Map<String, dynamic>))
        .toList();
    return FeedPage(
      items: items,
      page: json['page'] as int? ?? page,
      totalPages: json['total_pages'] as int? ?? 1,
      loggedIn: json['logged_in'] == true,
    );
  }

  /// All categories (for the picker).
  Future<List<PostCategory>> categories() async {
    final res =
        await _http.get(Uri.parse('${Config.apiBase}/categories'), headers: await _headers());
    final data = _decodeList(res);
    return data.map((e) => PostCategory.fromJson(e as Map<String, dynamic>)).toList();
  }

  /// The logged-in member's selected category IDs.
  Future<List<int>> getPreferences() async {
    final res =
        await _http.get(Uri.parse('${Config.apiBase}/preferences'), headers: await _headers());
    final json = _decode(res);
    return (json['categories'] as List<dynamic>? ?? [])
        .map((e) => e as int)
        .toList();
  }

  /// Save the member's selected category IDs.
  Future<List<int>> setPreferences(List<int> categoryIds) async {
    final res = await _http.post(
      Uri.parse('${Config.apiBase}/preferences'),
      headers: {...await _headers(), 'Content-Type': 'application/json'},
      body: jsonEncode({'categories': categoryIds}),
    );
    final json = _decode(res);
    return (json['categories'] as List<dynamic>? ?? [])
        .map((e) => e as int)
        .toList();
  }

  /// Verify credentials by hitting an authenticated endpoint. Returns
  /// true when the stored credentials are accepted.
  Future<bool> verifyCredentials() async {
    final res =
        await _http.get(Uri.parse('${Config.apiBase}/preferences'), headers: await _headers());
    return res.statusCode == 200;
  }

  Map<String, dynamic> _decode(http.Response res) {
    if (res.statusCode == 401 || res.statusCode == 403) {
      throw ApiException('Sign-in failed — check your username and application password.', res.statusCode);
    }
    if (res.statusCode >= 400) {
      throw ApiException('The server returned an error (${res.statusCode}).', res.statusCode);
    }
    final body = jsonDecode(res.body);
    if (body is Map<String, dynamic>) return body;
    throw ApiException('Unexpected response from the server.');
  }

  List<dynamic> _decodeList(http.Response res) {
    if (res.statusCode >= 400) {
      throw ApiException('The server returned an error (${res.statusCode}).', res.statusCode);
    }
    final body = jsonDecode(res.body);
    if (body is List) return body;
    throw ApiException('Unexpected response from the server.');
  }

  void dispose() => _http.close();
}
