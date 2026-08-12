import 'dart:convert';
import '../core/config.dart';
import '../core/storage.dart';
import 'invoicing_api.dart';
import 'local_db.dart';
import 'models.dart';

/// Motor de sincronização offline-first (espelha pwa-invoicing.js).
class SyncEngine {
  final _api = InvoicingApi();
  bool _syncing = false;

  bool get isSyncing => _syncing;

  /// Sync completo: repõe falhados, envia fila e descarrega catálogo.
  Future<SyncOutcome> sync({bool force = false}) async {
    if (_syncing) return SyncOutcome(false, 'Já a sincronizar');
    _syncing = true;
    try {
      // Operações de BD podem falhar (ex.: web sem sqflite) — proteger.
      try {
        final cv = await Storage.getMeta('catalog_version');
        if (cv != AppConfig.catalogVersion) {
          await LocalDb.clearProducts();
          await Storage.setMeta('last_sync', null);
          await Storage.setMeta('catalog_version', AppConfig.catalogVersion);
        }
        if (force) await LocalDb.resetFailedJobs();
        await _processQueue(); // envia fila pendente
      } catch (_) {/* sem BD local — continua */}

      // Descarregar catálogo
      final last = force ? null : await Storage.getMeta('last_sync');
      final json = await _api.sync(since: last is String ? last : null);

      // 1) Metadados PRIMEIRO (shared_preferences — funciona em web/desktop/mobile).
      //    Garante que empresa/módulos/turno ficam guardados mesmo sem BD local.
      await Storage.setMeta('last_sync', json['server_time']);
      await Storage.setMeta('company', json['company']);
      await Storage.setMeta('shift', json['shift']);
      await Storage.setMeta('modules', json['modules'] ?? []);
      if (json['tenant_id'] is int) await Storage.setTenantId(json['tenant_id']);

      // 2) Catálogo — sempre disponível em memória (web/desktop/mobile)
      final data = (json['data'] ?? {}) as Map;
      final products = (data['products'] as List? ?? [])
          .map((e) => Product.fromApi(Map<String, dynamic>.from(e)))
          .toList();
      final clients = (data['clients'] as List? ?? [])
          .map((e) => ClientModel.fromApi(Map<String, dynamic>.from(e)))
          .toList();

      // 3) Persistir na BD local (protegido — pode não existir em web)
      try {
        if (products.isNotEmpty) await LocalDb.upsertProducts(products);
        if (clients.isNotEmpty) await LocalDb.upsertClientsFromApi(clients);
      } catch (_) {/* sem BD local — fica em memória */}

      return SyncOutcome(true, 'Sincronizado', products: products, clients: clients);
    } catch (e) {
      return SyncOutcome(false, e.toString());
    } finally {
      _syncing = false;
    }
  }

  Future<void> _processQueue() async {
    final jobs = await LocalDb.pendingJobs();
    for (final job in jobs) {
      final id = job['id'] as int;
      final op = job['op'] as String;
      final payload = Map<String, dynamic>.from(jsonDecode(job['payload'] as String));
      final retries = (job['retries'] ?? 0) as int;
      try {
        if (op == 'create_pos_sale') {
          final res = await _api.createPosSale(payload);
          if (res['success'] == true) {
            await LocalDb.markSaleSynced(payload['local_uuid'] as String, res);
          } else {
            throw Exception(res['error']?.toString() ?? 'Falha na venda');
          }
        } else if (op == 'create_client') {
          await _api.createClient(payload);
        } else if (op == 'create_draft') {
          await _api.createDraft(payload);
        }
        await LocalDb.markJobDone(id);
      } catch (e) {
        await LocalDb.bumpJobRetry(id, retries, e.toString());
      }
    }
  }

  Future<Map<String, dynamic>?> getShift() async {
    final s = await Storage.getMeta('shift');
    return s is Map ? Map<String, dynamic>.from(s) : null;
  }

  Future<Map<String, dynamic>?> getCompany() async {
    final c = await Storage.getMeta('company');
    return c is Map ? Map<String, dynamic>.from(c) : null;
  }

  Future<List<Map<String, dynamic>>> getModules() async {
    final m = await Storage.getMeta('modules');
    if (m is List) {
      return m.map((e) => Map<String, dynamic>.from(e as Map)).toList();
    }
    return [];
  }
}

class SyncOutcome {
  final bool ok;
  final String message;
  final List<Product>? products;
  final List<ClientModel>? clients;
  SyncOutcome(this.ok, this.message, {this.products, this.clients});
}
