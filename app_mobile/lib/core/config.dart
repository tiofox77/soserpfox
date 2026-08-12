/// Configuração global da app de Faturação.
class AppConfig {
  /// Base do backend. Em produção: https://soserp.vip
  /// Para emulador Android a apontar para Laragon local use http://10.0.2.2
  static const String baseUrl = 'https://soserp.vip';

  static String get apiBase => '$baseUrl/api/v1';
  static String get invoicingApi => '$apiBase/invoicing';
  static String get authApi => '$apiBase/auth';

  /// Versão do payload do catálogo — incrementar quando o formato mudar
  /// (força re-sync completo, igual ao CATALOG_VERSION da PWA).
  static const int catalogVersion = 2;
}
