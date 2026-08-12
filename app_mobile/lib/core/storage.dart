import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

/// Armazenamento de token + metadados (last_sync, company, shift, modules, tenant).
/// Usa shared_preferences (multiplataforma, sem dependências nativas extra).
class Storage {
  static const _kToken = 'auth_token';
  static const _kTenant = 'tenant_id';

  // ---- Token ----
  static Future<void> setToken(String token) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_kToken, token);
  }

  static Future<String?> getToken() async {
    final p = await SharedPreferences.getInstance();
    return p.getString(_kToken);
  }

  static Future<void> clearToken() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_kToken);
  }

  // ---- Tenant ativo ----
  static Future<void> setTenantId(int id) async {
    final p = await SharedPreferences.getInstance();
    await p.setInt(_kTenant, id);
  }

  static Future<int?> getTenantId() async {
    final p = await SharedPreferences.getInstance();
    return p.getInt(_kTenant);
  }

  // ---- Meta genérico (JSON) ----
  static Future<void> setMeta(String key, Object? value) async {
    final p = await SharedPreferences.getInstance();
    await p.setString('meta_$key', jsonEncode(value));
  }

  static Future<dynamic> getMeta(String key) async {
    final p = await SharedPreferences.getInstance();
    final raw = p.getString('meta_$key');
    if (raw == null) return null;
    return jsonDecode(raw);
  }

  static Future<void> clearAll() async {
    final p = await SharedPreferences.getInstance();
    await p.clear();
  }
}
