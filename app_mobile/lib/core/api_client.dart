import 'package:dio/dio.dart';
import 'config.dart';
import 'storage.dart';

/// Cliente HTTP (Dio) com token Bearer e header de tenant.
class ApiClient {
  ApiClient._() {
    dio = Dio(BaseOptions(
      baseUrl: AppConfig.baseUrl,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 30),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      // Não lançar para 4xx — tratamos os erros manualmente
      validateStatus: (s) => s != null && s < 500,
    ));

    dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await Storage.getToken();
        if (token != null) options.headers['Authorization'] = 'Bearer $token';
        final tenant = await Storage.getTenantId();
        if (tenant != null) options.headers['X-Tenant-Id'] = tenant;
        handler.next(options);
      },
    ));
  }

  static final ApiClient instance = ApiClient._();
  late final Dio dio;
}
