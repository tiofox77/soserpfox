import 'package:flutter/foundation.dart';
import '../data/local_db.dart';
import '../data/models.dart';
import '../data/sync_engine.dart';

/// Estado global: catálogo (produtos/clientes), carrinho POS e sincronização.
class AppState extends ChangeNotifier {
  final SyncEngine sync = SyncEngine();

  List<Product> products = [];
  List<ClientModel> clients = [];
  Map<String, dynamic>? shift;
  Map<String, dynamic>? company;
  List<Map<String, dynamic>> modules = []; // módulos ativos do plano do tenant
  int pendingCount = 0;
  bool syncing = false;
  String? lastSyncMessage;

  /// Modo demonstração (dados em memória, sem backend/BD) — para correr em web/desktop.
  bool demoMode = false;

  // ---- Navegação tipo website (shell + sidebar) ----
  String currentRoute = 'home';
  Map<String, dynamic>? activeModule; // módulo selecionado (para placeholder)

  void navigate(String route, {Map<String, dynamic>? module}) {
    currentRoute = route;
    activeModule = module;
    notifyListeners();
  }

  // ---- Carrinho ----
  final List<CartItem> cart = [];
  ClientModel? selectedClient;
  String paymentMethod = 'CASH';

  double get cartSubtotal => cart.fold(0, (s, i) => s + i.net);
  double get cartTax => cart.fold(0, (s, i) => s + i.tax);
  double get cartTotal => cart.fold(0, (s, i) => s + i.total);
  int get cartCount => cart.fold(0, (s, i) => s + i.quantity);
  bool get shiftOpen => (shift?['open'] ?? false) == true;

  Future<void> loadLocal() async {
    if (demoMode) { notifyListeners(); return; }

    // 1) Metadados (shared_preferences) — funcionam em TODAS as plataformas (incl. web)
    shift = await sync.getShift();
    company = await sync.getCompany();
    modules = await sync.getModules();
    // Fallback: garantir que há sempre menus visíveis (Faturação é core/sempre-ativo)
    if (modules.isEmpty) {
      modules = [
        {'slug': 'invoicing', 'name': 'Faturação'},
        {'slug': 'treasury', 'name': 'Tesouraria'},
      ];
    }

    // 2) BD local (sqflite) — pode não estar disponível (ex.: web sem suporte);
    //    proteger para não impedir o resto da app de carregar.
    try {
      products = await LocalDb.products();
      clients = await LocalDb.clients();
      pendingCount = await LocalDb.pendingSalesCount();
    } catch (e) {
      // ignora — menus/módulos continuam visíveis
    }

    notifyListeners();
  }

  Future<void> doSync({bool force = false}) async {
    if (demoMode) return;
    syncing = true;
    notifyListeners();
    final out = await sync.sync(force: force);
    lastSyncMessage = out.message;
    syncing = false;
    await loadLocal();
    // Catálogo em memória vindo do sync (essencial em web, onde a BD pode não existir)
    if (out.ok) {
      if (out.products != null && out.products!.isNotEmpty) products = out.products!;
      if (out.clients != null && out.clients!.isNotEmpty) clients = out.clients!;
      notifyListeners();
    }
  }

  /// Carrega dados de exemplo (sem backend nem BD) para demonstração no browser.
  void loadDemo() {
    demoMode = true;
    shift = {'open': true, 'number': 'TRN-DEMO-001'};
    company = {'name': 'Farmácia Demo', 'nif': '5417289442'};
    modules = [
      {'slug': 'invoicing', 'name': 'Faturação', 'icon': 'receipt'},
      {'slug': 'treasury', 'name': 'Tesouraria', 'icon': 'wallet'},
      {'slug': 'contabilidade', 'name': 'Contabilidade', 'icon': 'calculator'},
      {'slug': 'rh', 'name': 'Recursos Humanos', 'icon': 'users'},
      {'slug': 'hotel', 'name': 'Hotel', 'icon': 'hotel'},
      {'slug': 'salon', 'name': 'Salão', 'icon': 'spa'},
    ];
    pendingCount = 0;
    products = [
      Product(id: 1, name: 'PARACETAMOL 500mg', sku: 'P001', type: 'produto', price: 500, taxRate: 14, stockQuantity: 102, category: 'Comprimidos'),
      Product(id: 2, name: 'AMOXICILINA 250mg', sku: 'A002', type: 'produto', price: 300, taxRate: 14, stockQuantity: 41, category: 'Cápsulas'),
      Product(id: 3, name: 'IBUPROFENO 400mg', sku: 'I003', type: 'produto', price: 500, taxRate: 14, stockQuantity: 67, category: 'Comprimidos'),
      Product(id: 4, name: 'VITAMINA C', sku: 'V004', type: 'produto', price: 800, taxRate: 14, stockQuantity: 30, category: 'Suplementos'),
      Product(id: 5, name: 'XAROPE TOSSE', sku: 'X005', type: 'produto', price: 1200, taxRate: 14, stockQuantity: 8, category: 'Xaropes'),
      Product(id: 6, name: 'BICARBONATO SÓDIO', sku: 'B006', type: 'produto', price: 1100, taxRate: 14, stockQuantity: 1, category: 'Outros'),
      Product(id: 7, name: 'OMEPRAZOL 20mg', sku: 'O007', type: 'produto', price: 1000, taxRate: 14, stockQuantity: 0, category: 'Cápsulas'),
      Product(id: 8, name: 'CONSULTA FARMACÊUTICA', sku: 'S008', type: 'servico', price: 2000, taxRate: 14, stockQuantity: 0, category: 'Serviços'),
    ];
    clients = [
      ClientModel(id: 1, name: 'Consumidor Final', nif: '999999999', type: 'pessoa_fisica'),
      ClientModel(id: 2, name: 'Clínica Sagrada Esperança', nif: '5000123456', type: 'pessoa_juridica'),
      ClientModel(id: 3, name: 'João Manuel', nif: '003456789LA042', type: 'pessoa_fisica'),
    ];
    notifyListeners();
  }

  // ---- Operações de carrinho ----
  void addToCart(Product p) {
    final existing = cart.where((i) => i.productId == p.id).toList();
    if (existing.isNotEmpty) {
      existing.first.quantity++;
    } else {
      cart.add(CartItem(
        productId: p.id,
        productName: p.name,
        unitPrice: p.price,
        taxRate: p.taxRate,
      ));
    }
    notifyListeners();
  }

  int qtyInCart(Product p) =>
      cart.where((i) => i.productId == p.id).fold(0, (s, i) => s + i.quantity);

  void increment(int idx) {
    cart[idx].quantity++;
    notifyListeners();
  }

  void decrement(int idx) {
    if (cart[idx].quantity > 1) {
      cart[idx].quantity--;
    } else {
      cart.removeAt(idx);
    }
    notifyListeners();
  }

  void clearCart() {
    cart.clear();
    selectedClient = null;
    notifyListeners();
  }

  void setClient(ClientModel? c) {
    selectedClient = c;
    notifyListeners();
  }

  void setPayment(String code) {
    paymentMethod = code;
    notifyListeners();
  }
}
