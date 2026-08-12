import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../data/models.dart';
import '../../data/pos_service.dart';
import '../../state/app_state.dart';

/// POS (conteúdo, sem Scaffold — vive dentro do AppShell).
class PosBody extends StatefulWidget {
  const PosBody({super.key});
  @override
  State<PosBody> createState() => _PosBodyState();
}

class _PosBodyState extends State<PosBody> {
  String _search = '';
  String? _category;

  List<Product> _filtered(AppState app) {
    final s = _search.toLowerCase().trim();
    return app.products.where((p) {
      if (_category != null && p.category != _category) return false;
      if (s.isEmpty) return true;
      return p.name.toLowerCase().contains(s) ||
          (p.sku ?? '').toLowerCase().contains(s) ||
          (p.barcode ?? '').toLowerCase().contains(s);
    }).toList();
  }

  List<String> _categories(AppState app) {
    final set = <String>{};
    for (final p in app.products) {
      if (p.category != null && p.category!.isNotEmpty) set.add(p.category!);
    }
    final list = set.toList()..sort();
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final products = _filtered(app);
    final cats = _categories(app);

    return Stack(children: [
      Column(children: [
        // Estado do turno
        Container(
          width: double.infinity,
          color: app.shiftOpen ? const Color(0xFFECFDF5) : const Color(0xFFFEF2F2),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          child: Row(children: [
            Icon(app.shiftOpen ? Icons.lock_open : Icons.warning_amber, size: 15,
                color: app.shiftOpen ? AppColors.emerald : AppColors.red),
            const SizedBox(width: 6),
            Text(app.shiftOpen ? 'Turno aberto · ${app.shift?['number'] ?? ''}' : 'Sem turno aberto',
                style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600,
                    color: app.shiftOpen ? const Color(0xFF065F46) : const Color(0xFF991B1B))),
          ]),
        ),
        // Pesquisa
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Pesquisar ou scan código…', prefixIcon: Icon(Icons.search)),
            onChanged: (v) => setState(() => _search = v),
          ),
        ),
        // Categorias
        SizedBox(
          height: 40,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            children: [
              _catChip('Todos', _category == null, () => setState(() => _category = null)),
              ...cats.map((c) => _catChip(c, _category == c, () => setState(() => _category = c))),
            ],
          ),
        ),
        // Grelha
        Expanded(
          child: products.isEmpty
              ? Center(child: Text(app.products.isEmpty ? 'Sem produtos sincronizados' : 'Nenhum produto', style: const TextStyle(color: AppColors.slate500)))
              : GridView.builder(
                  padding: EdgeInsets.fromLTRB(12, 10, 12, app.cart.isEmpty ? 12 : 84),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(
                    maxCrossAxisExtent: 180, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 0.82),
                  itemCount: products.length,
                  itemBuilder: (_, i) => _productCard(app, products[i]),
                ),
        ),
      ]),
      if (app.cart.isNotEmpty)
        Positioned(left: 10, right: 10, bottom: 10, child: _cartBar(app)),
    ]);
  }

  Widget _catChip(String label, bool active, VoidCallback onTap) => Padding(
        padding: const EdgeInsets.only(right: 8),
        child: ChoiceChip(
          label: Text(label),
          selected: active,
          onSelected: (_) => onTap(),
          selectedColor: AppColors.blue,
          labelStyle: TextStyle(color: active ? Colors.white : AppColors.slate900, fontWeight: FontWeight.w600, fontSize: 12),
          backgroundColor: Colors.white,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        ),
      );

  Widget _productCard(AppState app, Product p) {
    final qty = app.qtyInCart(p);
    return InkWell(
      onTap: () => app.addToCart(p),
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.slate200)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: Stack(children: [
              Container(
                width: double.infinity,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: [Color(0xFFEFF6FF), Color(0xFFE0E7FF)]),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(p.isService ? Icons.room_service : Icons.inventory_2, color: AppColors.blue, size: 34),
              ),
              if (qty > 0)
                Positioned(top: 4, right: 4,
                  child: Container(padding: const EdgeInsets.all(5),
                    decoration: const BoxDecoration(color: AppColors.emerald, shape: BoxShape.circle),
                    child: Text('$qty', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold)))),
            ]),
          ),
          const SizedBox(height: 6),
          Text(p.name, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, height: 1.1)),
          const SizedBox(height: 2),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(formatMoney(p.price), style: const TextStyle(color: AppColors.blue, fontWeight: FontWeight.bold, fontSize: 13)),
            if (!p.isService)
              Text(p.stockQuantity > 0 ? '${p.stockQuantity.toInt()}' : 'Esgot.',
                  style: TextStyle(fontSize: 10, color: p.stockQuantity > 0 ? AppColors.slate500 : AppColors.red, fontWeight: FontWeight.w600)),
          ]),
        ]),
      ),
    );
  }

  Widget _cartBar(AppState app) => GestureDetector(
        onTap: () => _openCart(app),
        child: Container(
          height: 56,
          padding: const EdgeInsets.symmetric(horizontal: 18),
          decoration: BoxDecoration(
            gradient: AppColors.emeraldGradient,
            borderRadius: BorderRadius.circular(18),
            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.25), blurRadius: 12, offset: const Offset(0, 4))],
          ),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Row(children: [
              CircleAvatar(radius: 14, backgroundColor: Colors.white.withValues(alpha: 0.25),
                  child: Text('${app.cartCount}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12))),
              const SizedBox(width: 10),
              const Text('Ver carrinho', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ]),
            Text('${formatMoney(app.cartTotal)} Kz', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
          ]),
        ),
      );

  void _openCart(AppState app) {
    showModalBottomSheet(
      context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => _CartSheet(app: app, onCheckout: _checkout),
    );
  }

  Future<void> _checkout(AppState app, String payment, double? received) async {
    Navigator.of(context).pop();
    Map<String, dynamic> sale;
    if (app.demoMode) {
      sale = {
        'provisional_number': 'PEND-DEMO-${DateTime.now().millisecondsSinceEpoch % 1000000}',
        'client_name': app.selectedClient?.name ?? 'Consumidor Final',
        'total': app.cartTotal,
      };
      app.clearCart();
    } else {
      sale = await PosService.createSaleOffline(
        items: List.from(app.cart), client: app.selectedClient, paymentMethod: payment, amountReceived: received);
      app.clearCart();
      await app.loadLocal();
      app.doSync();
    }
    if (mounted) _showReceipt(sale);
  }

  void _showReceipt(Map<String, dynamic> sale) {
    showDialog(
      context: context,
      builder: (_) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(children: const [
          Icon(Icons.check_circle, color: AppColors.emerald),
          SizedBox(width: 8),
          Expanded(child: Text('Venda registada', style: TextStyle(fontSize: 18))),
        ]),
        content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Documento: ${sale['provisional_number']}'),
          Text('Cliente: ${sale['client_name']}'),
          Text('Total: ${formatMoney(sale['total'])} Kz', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.emerald, fontSize: 16)),
          const SizedBox(height: 6),
          const Text('A sincronizar com o servidor para obter o nº AGT…', style: TextStyle(fontSize: 12, color: AppColors.slate500)),
        ]),
        actions: [TextButton(onPressed: () => Navigator.pop(context), child: const Text('Nova venda'))],
      ),
    );
  }
}

class _CartSheet extends StatefulWidget {
  final AppState app;
  final Future<void> Function(AppState, String, double?) onCheckout;
  const _CartSheet({required this.app, required this.onCheckout});
  @override
  State<_CartSheet> createState() => _CartSheetState();
}

class _CartSheetState extends State<_CartSheet> {
  @override
  Widget build(BuildContext context) {
    final app = widget.app;
    return AnimatedPadding(
      duration: const Duration(milliseconds: 150),
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * 0.9),
        decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        child: AnimatedBuilder(
          animation: app,
          builder: (_, __) => Column(mainAxisSize: MainAxisSize.min, children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
              child: Row(children: [
                const Icon(Icons.shopping_cart, color: AppColors.emerald),
                const SizedBox(width: 8),
                const Text('Carrinho', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                const Spacer(),
                if (app.cart.isNotEmpty)
                  TextButton.icon(
                    onPressed: () { app.clearCart(); Navigator.pop(context); },
                    icon: const Icon(Icons.delete_outline, color: AppColors.red, size: 18),
                    label: const Text('Limpar', style: TextStyle(color: AppColors.red))),
              ]),
            ),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: app.cart.length,
                itemBuilder: (_, idx) {
                  final it = app.cart[idx];
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(color: AppColors.slate50, borderRadius: BorderRadius.circular(14)),
                    child: Row(children: [
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(it.productName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                          Text('${formatMoney(it.unitPrice)} · IVA ${it.taxRate.toInt()}%', style: const TextStyle(fontSize: 11, color: AppColors.slate500)),
                        ]),
                      ),
                      _qtyBtn(Icons.remove, AppColors.red, () => app.decrement(idx)),
                      SizedBox(width: 28, child: Center(child: Text('${it.quantity}', style: const TextStyle(fontWeight: FontWeight.bold)))),
                      _qtyBtn(Icons.add, AppColors.emerald, () => app.increment(idx)),
                    ]),
                  );
                },
              ),
            ),
            const Divider(height: 1),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(children: [
                InkWell(
                  onTap: () => _pickClient(app),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 12),
                    decoration: BoxDecoration(color: const Color(0xFFEFF6FF), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFBFDBFE))),
                    child: Row(children: [
                      const Icon(Icons.person, color: AppColors.blue, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(app.selectedClient?.name ?? 'Consumidor Final')),
                      const Icon(Icons.chevron_right, color: AppColors.blue),
                    ]),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(gradient: AppColors.emeraldGradient, borderRadius: BorderRadius.circular(16)),
                  child: Column(children: [
                    _totalRow('Subtotal', app.cartSubtotal),
                    _totalRow('IVA', app.cartTax),
                    const Divider(color: Colors.white30),
                    Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      const Text('TOTAL', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                      Text('${formatMoney(app.cartTotal)} Kz', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 22)),
                    ]),
                  ]),
                ),
                const SizedBox(height: 12),
                Row(
                  children: PaymentMethod.defaults.map((m) {
                    final active = app.paymentMethod == m.code;
                    return Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 3),
                        child: InkWell(
                          onTap: () => app.setPayment(m.code),
                          child: Container(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            decoration: BoxDecoration(
                              color: active ? AppColors.blue : Colors.white,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: active ? AppColors.blue : AppColors.slate200, width: 2),
                            ),
                            child: Column(children: [
                              Icon(_payIcon(m.icon), size: 18, color: active ? Colors.white : AppColors.slate500),
                              const SizedBox(height: 2),
                              Text(m.label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: active ? Colors.white : AppColors.slate500)),
                            ]),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 14),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: app.cart.isEmpty ? null : () => widget.onCheckout(app, app.paymentMethod, null),
                    icon: const Icon(Icons.check_circle),
                    label: const Text('Finalizar Venda'),
                  ),
                ),
              ]),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _qtyBtn(IconData icon, Color color, VoidCallback onTap) => InkWell(
        onTap: onTap,
        child: Container(width: 30, height: 30, decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8)), child: Icon(icon, color: Colors.white, size: 16)),
      );

  Widget _totalRow(String label, double v) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 1),
        child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
          Text('${formatMoney(v)} Kz', style: const TextStyle(color: Colors.white70, fontSize: 12)),
        ]),
      );

  IconData _payIcon(String key) {
    switch (key) {
      case 'card': return Icons.credit_card;
      case 'bank': return Icons.account_balance;
      case 'mobile': return Icons.smartphone;
      default: return Icons.payments;
    }
  }

  void _pickClient(AppState app) {
    showModalBottomSheet(
      context: context, isScrollControlled: true,
      builder: (_) => SafeArea(
        child: SizedBox(
          height: MediaQuery.of(context).size.height * 0.7,
          child: Column(children: [
            ListTile(leading: const Icon(Icons.person_outline), title: const Text('Consumidor Final'),
                onTap: () { app.setClient(null); Navigator.pop(context); }),
            const Divider(height: 1),
            Expanded(
              child: ListView.builder(
                itemCount: app.clients.length,
                itemBuilder: (_, i) {
                  final c = app.clients[i];
                  return ListTile(
                    title: Text(c.name),
                    subtitle: c.nif != null ? Text('NIF: ${c.nif}') : null,
                    onTap: () { app.setClient(c); Navigator.pop(context); },
                  );
                },
              ),
            ),
          ]),
        ),
      ),
    );
  }
}
