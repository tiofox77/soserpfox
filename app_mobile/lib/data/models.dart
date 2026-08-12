// Modelos de dados do módulo de Faturação.

class Product {
  final int id;
  final String name;
  final String? sku;
  final String? barcode;
  final String? type;
  final double price;
  final double taxRate;
  final double stockQuantity;
  final String? category;

  Product({
    required this.id,
    required this.name,
    this.sku,
    this.barcode,
    this.type,
    required this.price,
    required this.taxRate,
    required this.stockQuantity,
    this.category,
  });

  bool get isService => type == 'servico';

  factory Product.fromApi(Map<String, dynamic> m) => Product(
        id: m['id'] as int,
        name: (m['name'] ?? '').toString(),
        sku: m['sku']?.toString(),
        barcode: m['barcode']?.toString(),
        type: m['type']?.toString(),
        price: _d(m['price']),
        taxRate: _d(m['tax_rate'], 14),
        stockQuantity: _d(m['stock_quantity']),
        category: m['category']?.toString(),
      );

  Map<String, dynamic> toDb() => {
        'id': id,
        'name': name,
        'sku': sku,
        'barcode': barcode,
        'type': type,
        'price': price,
        'tax_rate': taxRate,
        'stock_quantity': stockQuantity,
        'category': category,
      };

  factory Product.fromDb(Map<String, dynamic> m) => Product(
        id: m['id'] as int,
        name: m['name'] as String,
        sku: m['sku'] as String?,
        barcode: m['barcode'] as String?,
        type: m['type'] as String?,
        price: _d(m['price']),
        taxRate: _d(m['tax_rate'], 14),
        stockQuantity: _d(m['stock_quantity']),
        category: m['category'] as String?,
      );
}

class ClientModel {
  final int? id; // null se ainda só local
  final String? localUuid;
  final String name;
  final String? nif;
  final String type; // pessoa_fisica | pessoa_juridica
  final bool synced;

  ClientModel({
    this.id,
    this.localUuid,
    required this.name,
    this.nif,
    this.type = 'pessoa_fisica',
    this.synced = true,
  });

  bool get isCompany => type == 'pessoa_juridica';

  factory ClientModel.fromApi(Map<String, dynamic> m) => ClientModel(
        id: m['id'] as int?,
        name: (m['name'] ?? '').toString(),
        nif: m['nif']?.toString(),
        type: (m['type'] ?? 'pessoa_fisica').toString(),
        synced: true,
      );

  Map<String, dynamic> toDb() => {
        'id': id,
        'local_uuid': localUuid,
        'name': name,
        'nif': nif,
        'type': type,
        'synced': synced ? 1 : 0,
      };

  factory ClientModel.fromDb(Map<String, dynamic> m) => ClientModel(
        id: m['id'] as int?,
        localUuid: m['local_uuid'] as String?,
        name: m['name'] as String,
        nif: m['nif'] as String?,
        type: (m['type'] ?? 'pessoa_fisica').toString(),
        synced: (m['synced'] ?? 1) == 1,
      );
}

class PaymentMethod {
  final String code;
  final String label;
  final String icon; // material icon name handled na UI
  const PaymentMethod(this.code, this.label, this.icon);

  static const defaults = [
    PaymentMethod('CASH', 'Dinheiro', 'cash'),
    PaymentMethod('TPA', 'TPA', 'card'),
    PaymentMethod('TRANSFER', 'Transf.', 'bank'),
    PaymentMethod('MCX', 'Multic.', 'mobile'),
  ];
}

/// Item do carrinho POS.
class CartItem {
  final int? productId;
  final String productName;
  int quantity;
  final double unitPrice;
  final double taxRate;

  CartItem({
    this.productId,
    required this.productName,
    this.quantity = 1,
    required this.unitPrice,
    this.taxRate = 14,
  });

  double get net => quantity * unitPrice;
  double get tax => net * taxRate / 100;
  double get total => net + tax;

  Map<String, dynamic> toPayload() => {
        'product_id': productId,
        'product_name': productName,
        'quantity': quantity,
        'unit_price': unitPrice,
        'tax_rate': taxRate,
        'is_service': false,
        'unit': 'UN',
      };
}

double _d(dynamic v, [double def = 0]) {
  if (v == null) return def;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString()) ?? def;
}
