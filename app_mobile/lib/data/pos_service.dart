import 'dart:convert';
import 'dart:math';
import 'local_db.dart';
import 'models.dart';

/// Cria vendas POS offline (grava local + enfileira), idempotente por local_uuid.
/// Espelha SosPwa.createPosSaleOffline.
class PosService {
  static String _uuid() {
    final r = Random();
    final rand = List.generate(6, (_) => r.nextInt(36).toRadixString(36)).join();
    return 'pos_${DateTime.now().millisecondsSinceEpoch}_$rand';
  }

  static String _provisional(String localUuid) {
    final d = DateTime.now();
    final ymd = '${d.year}${d.month.toString().padLeft(2, '0')}${d.day.toString().padLeft(2, '0')}';
    return 'PEND-$ymd-${localUuid.substring(localUuid.length - 6).toUpperCase()}';
  }

  /// Devolve o registo gravado (com provisional_number).
  static Future<Map<String, dynamic>> createSaleOffline({
    required List<CartItem> items,
    ClientModel? client,
    required String paymentMethod,
    double discountCommercial = 0,
    double? amountReceived,
  }) async {
    final localUuid = _uuid();
    final createdAt = DateTime.now().toIso8601String();

    double subtotal = 0, tax = 0;
    for (final i in items) {
      subtotal += i.net;
      tax += i.tax;
    }
    final discount = subtotal * discountCommercial / 100;
    final base = subtotal - discount;
    final taxAfter = tax * (subtotal > 0 ? base / subtotal : 1);
    final total = base + taxAfter;

    final clientName = client?.name ?? 'Consumidor Final';
    final provisional = _provisional(localUuid);

    final record = {
      'local_uuid': localUuid,
      'provisional_number': provisional,
      'client_name': clientName,
      'payment_method': paymentMethod,
      'total': double.parse(total.toStringAsFixed(2)),
      'items': jsonEncode(items.map((i) => i.toPayload()).toList()),
      'created_at': createdAt,
      'synced': 0,
    };
    await LocalDb.insertPosSale(record);

    final payload = {
      'local_uuid': localUuid,
      'client_id': client?.id,
      'client_local_uuid': client?.id == null ? client?.localUuid : null,
      'client_name': clientName,
      'client_nif': client?.nif ?? '999999999',
      'payment_method': paymentMethod,
      'amount_received': amountReceived ?? total,
      'discount_commercial': discountCommercial,
      'notes': 'POS Mobile · Pagamento: $paymentMethod',
      'created_at_local': createdAt,
      'items': items.map((i) => i.toPayload()).toList(),
    };
    await LocalDb.enqueue('create_pos_sale', payload);

    return record;
  }
}
