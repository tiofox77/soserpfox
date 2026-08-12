import '../core/api_client.dart';
import '../core/config.dart';
import '../core/storage.dart';

/// Autenticação por token (Sanctum) — requer endpoint POST /api/v1/auth/login no backend.
/// Ver docs/flutter-invoicing-app.md §2 e §11.
class AuthApi {
  final _dio = ApiClient.instance.dio;

  Future<AuthResult> login(String email, String password, {String device = 'flutter'}) async {
    final r = await _dio.post('${AppConfig.authApi}/login', data: {
      'email': email,
      'password': password,
      'device_name': device,
    });

    if (r.statusCode == 200 && r.data is Map && r.data['token'] != null) {
      final token = r.data['token'].toString();
      await Storage.setToken(token);
      final tenantId = r.data['tenant_id'];
      if (tenantId is int) await Storage.setTenantId(tenantId);
      return AuthResult.ok(
        userName: r.data['user']?['name']?.toString() ?? 'Utilizador',
      );
    }

    final msg = (r.data is Map ? (r.data['message'] ?? r.data['error']) : null)?.toString();
    return AuthResult.fail(msg ?? 'Credenciais inválidas (HTTP ${r.statusCode})');
  }

  Future<void> logout() async {
    await Storage.clearAll();
  }

  Future<bool> isLoggedIn() async => (await Storage.getToken()) != null;
}

class AuthResult {
  final bool success;
  final String? userName;
  final String? error;
  AuthResult.ok({this.userName}) : success = true, error = null;
  AuthResult.fail(this.error) : success = false, userName = null;
}
