import 'dart:convert';
import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';
import 'models.dart';

/// Base de dados local (sqflite) — espelha os stores do IndexedDB da PWA.
class LocalDb {
  static Database? _db;

  static Future<Database> get db async {
    if (_db != null) return _db!;
    final path = p.join(await getDatabasesPath(), 'soserp_faturacao.db');
    _db = await openDatabase(
      path,
      version: 1,
      onCreate: (d, v) async {
        await d.execute('''
          CREATE TABLE products (
            id INTEGER PRIMARY KEY, name TEXT, sku TEXT, barcode TEXT, type TEXT,
            price REAL, tax_rate REAL, stock_quantity REAL, category TEXT
          )''');
        await d.execute('''
          CREATE TABLE clients (
            id INTEGER, local_uuid TEXT, name TEXT, nif TEXT, type TEXT, synced INTEGER,
            PRIMARY KEY (rowid)
          )''');
        await d.execute('''
          CREATE TABLE pos_sales (
            local_uuid TEXT PRIMARY KEY, provisional_number TEXT, client_name TEXT,
            payment_method TEXT, total REAL, items TEXT, created_at TEXT,
            synced INTEGER DEFAULT 0, server_id INTEGER, server_number TEXT, qr TEXT
          )''');
        await d.execute('''
          CREATE TABLE sync_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT, op TEXT, payload TEXT,
            status TEXT DEFAULT 'pending', retries INTEGER DEFAULT 0,
            last_error TEXT, created_at TEXT
          )''');
      },
    );
    return _db!;
  }

  // ---- Produtos ----
  static Future<void> upsertProducts(List<Product> items) async {
    final d = await db;
    final batch = d.batch();
    for (final pr in items) {
      batch.insert('products', pr.toDb(), conflictAlgorithm: ConflictAlgorithm.replace);
    }
    await batch.commit(noResult: true);
  }

  static Future<List<Product>> products() async {
    final d = await db;
    final rows = await d.query('products', orderBy: 'name');
    return rows.map(Product.fromDb).toList();
  }

  static Future<int> productsCount() async {
    final d = await db;
    return Sqflite.firstIntValue(await d.rawQuery('SELECT COUNT(*) FROM products')) ?? 0;
  }

  static Future<void> clearProducts() async {
    final d = await db;
    await d.delete('products');
  }

  // ---- Clientes ----
  static Future<void> upsertClientsFromApi(List<ClientModel> items) async {
    final d = await db;
    final batch = d.batch();
    for (final c in items) {
      // Substituir por id (clientes do servidor)
      batch.delete('clients', where: 'id = ?', whereArgs: [c.id]);
      batch.insert('clients', c.toDb());
    }
    await batch.commit(noResult: true);
  }

  static Future<int> insertLocalClient(ClientModel c) async {
    final d = await db;
    return d.insert('clients', c.toDb());
  }

  static Future<List<ClientModel>> clients() async {
    final d = await db;
    final rows = await d.query('clients', orderBy: 'name');
    return rows.map(ClientModel.fromDb).toList();
  }

  // ---- Vendas POS ----
  static Future<void> insertPosSale(Map<String, dynamic> sale) async {
    final d = await db;
    await d.insert('pos_sales', sale, conflictAlgorithm: ConflictAlgorithm.replace);
  }

  static Future<List<Map<String, dynamic>>> posSales() async {
    final d = await db;
    return d.query('pos_sales', orderBy: 'created_at DESC');
  }

  static Future<int> pendingSalesCount() async {
    final d = await db;
    return Sqflite.firstIntValue(
            await d.rawQuery('SELECT COUNT(*) FROM pos_sales WHERE synced = 0')) ??
        0;
  }

  static Future<void> markSaleSynced(String localUuid, Map<String, dynamic> res) async {
    final d = await db;
    await d.update(
      'pos_sales',
      {
        'synced': 1,
        'server_id': res['id'],
        'server_number': res['invoice_number'],
        'qr': res['qr_image'],
      },
      where: 'local_uuid = ?',
      whereArgs: [localUuid],
    );
  }

  // ---- Fila de sync ----
  static Future<void> enqueue(String op, Map<String, dynamic> payload) async {
    final d = await db;
    await d.insert('sync_queue', {
      'op': op,
      'payload': jsonEncode(payload),
      'status': 'pending',
      'retries': 0,
      'created_at': DateTime.now().toIso8601String(),
    });
  }

  static Future<List<Map<String, dynamic>>> pendingJobs() async {
    final d = await db;
    return d.query('sync_queue', where: "status = 'pending'", orderBy: 'created_at');
  }

  static Future<void> resetFailedJobs() async {
    final d = await db;
    await d.update('sync_queue', {'status': 'pending', 'retries': 0, 'last_error': null},
        where: "status = 'failed'");
  }

  static Future<void> markJobDone(int id) async {
    final d = await db;
    await d.update('sync_queue', {'status': 'done'}, where: 'id = ?', whereArgs: [id]);
  }

  static Future<void> bumpJobRetry(int id, int retries, String error) async {
    final d = await db;
    await d.update(
      'sync_queue',
      {'retries': retries + 1, 'last_error': error, 'status': retries + 1 >= 5 ? 'failed' : 'pending'},
      where: 'id = ?',
      whereArgs: [id],
    );
  }
}
