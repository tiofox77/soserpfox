import '../core/api_client.dart';
import '../core/config.dart';

/// Chamadas à API REST de faturação (api/v1/invoicing/*).
class InvoicingApi {
  final _dio = ApiClient.instance.dio;

  Future<Map<String, dynamic>> ping() async {
    final r = await _dio.get('${AppConfig.invoicingApi}/ping');
    return Map<String, dynamic>.from(r.data);
  }

  /// Sync do catálogo. [since] em ISO8601 para incremental.
  Future<Map<String, dynamic>> sync({String? since}) async {
    final r = await _dio.get(
      '${AppConfig.invoicingApi}/sync',
      queryParameters: since == null ? null : {'since': since},
    );
    if (r.statusCode != 200) {
      throw Exception('Sync falhou (${r.statusCode})');
    }
    return Map<String, dynamic>.from(r.data);
  }

  Future<Map<String, dynamic>> createClient(Map<String, dynamic> payload) async {
    final r = await _dio.post('${AppConfig.invoicingApi}/clients', data: payload);
    return Map<String, dynamic>.from(r.data);
  }

  Future<Map<String, dynamic>> createDraft(Map<String, dynamic> payload) async {
    final r = await _dio.post('${AppConfig.invoicingApi}/drafts', data: payload);
    return Map<String, dynamic>.from(r.data);
  }

  Future<Map<String, dynamic>> createPosSale(Map<String, dynamic> payload) async {
    final r = await _dio.post('${AppConfig.invoicingApi}/pos/sale', data: payload);
    return Map<String, dynamic>.from(r.data);
  }

  /// Listagem genérica de uma área de faturação (ex.: 'sales-invoices').
  Future<List<Map<String, dynamic>>> list(String area) async {
    final r = await _dio.get('${AppConfig.invoicingApi}/list/$area');
    if (r.statusCode != 200) {
      throw Exception('Falha ao carregar (${r.statusCode})');
    }
    final data = (r.data['data'] as List? ?? []);
    return data.map((e) => Map<String, dynamic>.from(e)).toList();
  }

  /// Estatísticas do dashboard de faturação.
  Future<Map<String, dynamic>> dashboardStats() async {
    final r = await _dio.get('${AppConfig.invoicingApi}/dashboard-stats');
    if (r.statusCode != 200) throw Exception('Falha (${r.statusCode})');
    return Map<String, dynamic>.from(r.data);
  }

  /// Detalhe de um documento (registo + itens).
  Future<Map<String, dynamic>> detail(String area, int id) async {
    final r = await _dio.get('${AppConfig.invoicingApi}/detail/$area/$id');
    if (r.statusCode != 200) throw Exception('Falha (${r.statusCode})');
    return Map<String, dynamic>.from(r.data);
  }

  // ---- CRUD (dados-mestre) ----
  Future<void> create(String area, Map<String, dynamic> data) async {
    final r = await _dio.post('${AppConfig.invoicingApi}/list/$area', data: data);
    if (r.statusCode != 201 && r.statusCode != 200) {
      throw Exception(_err(r));
    }
  }

  Future<void> updateItem(String area, int id, Map<String, dynamic> data) async {
    final r = await _dio.put('${AppConfig.invoicingApi}/list/$area/$id', data: data);
    if (r.statusCode != 200) throw Exception(_err(r));
  }

  Future<void> deleteItem(String area, int id) async {
    final r = await _dio.delete('${AppConfig.invoicingApi}/list/$area/$id');
    if (r.statusCode != 200) throw Exception(_err(r));
  }

  String _err(dynamic r) {
    try {
      final d = r.data;
      if (d is Map) {
        return (d['error'] ?? d['message'] ?? 'Erro (${r.statusCode})').toString();
      }
    } catch (_) {}
    return 'Erro (${r.statusCode})';
  }
}
