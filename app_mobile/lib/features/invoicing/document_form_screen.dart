import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../data/invoicing_api.dart';
import '../../data/models.dart';
import '../../state/app_state.dart';

/// Linha editável de um documento (fatura/proforma).
class DocLine {
  int? productId;
  String productName;
  double quantity;
  double unitPrice;
  double taxRate;
  double discountPercent;

  DocLine({
    this.productId,
    required this.productName,
    this.quantity = 1,
    this.unitPrice = 0,
    this.taxRate = 14,
    this.discountPercent = 0,
  });

  double get gross => quantity * unitPrice;
  double get discount => gross * discountPercent / 100;
  double get net => gross - discount;
  double get tax => net * taxRate / 100;
  double get total => net + tax;

  Map<String, dynamic> toPayload() => {
        'product_id': productId,
        'product_name': productName,
        'quantity': quantity,
        'unit_price': unitPrice,
        'tax_rate': taxRate,
        'discount_percent': discountPercent,
      };
}

/// Tipos de documento suportados pelo backend (DraftController).
const Map<String, ({String label, String docType, String hint})> kDocTypes = {
  'sales-invoices': (label: 'Fatura', docType: 'FT', hint: 'Fatura (FT)'),
  'sales-proformas': (label: 'Proforma', docType: 'proforma', hint: 'Proforma'),
};

class DocumentFormScreen extends StatefulWidget {
  final String area; // 'sales-invoices' | 'sales-proformas'
  const DocumentFormScreen({super.key, required this.area});

  @override
  State<DocumentFormScreen> createState() => _DocumentFormScreenState();
}

class _DocumentFormScreenState extends State<DocumentFormScreen> {
  final _api = InvoicingApi();
  final List<DocLine> _lines = [];
  ClientModel? _client;
  String _docType = 'FT'; // só relevante para faturas
  final _notesCtrl = TextEditingController();
  bool _saving = false;
  String? _error;

  bool get _isInvoice => widget.area == 'sales-invoices';
  ({String label, String docType, String hint}) get _cfg =>
      kDocTypes[widget.area] ?? (label: 'Documento', docType: 'FT', hint: 'Documento');

  double get _subtotal => _lines.fold(0, (s, l) => s + l.net);
  double get _tax => _lines.fold(0, (s, l) => s + l.tax);
  double get _total => _lines.fold(0, (s, l) => s + l.total);

  @override
  void initState() {
    super.initState();
    if (!_isInvoice) _docType = 'proforma';
  }

  @override
  void dispose() {
    _notesCtrl.dispose();
    super.dispose();
  }

  // ---------------- Picker de produto ----------------
  Future<void> _addLine() async {
    final app = context.read<AppState>();
    final product = await showModalBottomSheet<Product>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _ProductPicker(products: app.products),
    );
    if (product == null) return; // utilizador escolheu "linha manual" -> retorna sentinela?
    setState(() {
      _lines.add(DocLine(
        productId: product.id == -1 ? null : product.id,
        productName: product.name,
        unitPrice: product.price,
        taxRate: product.taxRate,
      ));
    });
  }

  Future<void> _editLine(int idx) async {
    final l = _lines[idx];
    final result = await showDialog<DocLine>(context: context, builder: (_) => _LineDialog(line: l));
    if (result != null) setState(() => _lines[idx] = result);
  }

  Future<void> _pickClient() async {
    final app = context.read<AppState>();
    final c = await showModalBottomSheet<ClientModel>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _ClientPicker(clients: app.clients),
    );
    if (c != null) setState(() => _client = c.id == -1 ? null : c);
  }

  Future<void> _save() async {
    if (_lines.isEmpty) {
      setState(() => _error = 'Adicione pelo menos um artigo.');
      return;
    }
    setState(() { _saving = true; _error = null; });
    try {
      final res = await _api.createDraft({
        'doc_type': _docType,
        'client_id': _client?.id,
        'notes': _notesCtrl.text.trim().isEmpty ? null : _notesCtrl.text.trim(),
        'invoice_date': DateTime.now().toIso8601String().substring(0, 10),
        'items': _lines.map((l) => l.toPayload()).toList(),
      });
      if (!mounted) return;
      final num = res['invoice_number'] ?? res['proforma_number'] ?? '#${res['id']}';
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text('${_cfg.label} criada: $num'),
        backgroundColor: AppColors.emerald, behavior: SnackBarBehavior.floating));
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _saving = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Nova ${_cfg.label}', style: const TextStyle(fontWeight: FontWeight.bold))),
      body: Column(children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (_error != null) _errorBox(_error!),
              if (_isInvoice) _docTypeSelector(),
              _clientCard(),
              const SizedBox(height: 16),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Artigos', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                TextButton.icon(onPressed: _addLine, icon: const Icon(Icons.add, size: 18), label: const Text('Adicionar')),
              ]),
              if (_lines.isEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 28),
                  alignment: Alignment.center,
                  child: const Text('Sem artigos. Toque em "Adicionar".', style: TextStyle(color: AppColors.slate500)),
                ),
              ..._lines.asMap().entries.map((e) => _lineCard(e.key, e.value)),
              const SizedBox(height: 14),
              TextField(
                controller: _notesCtrl,
                maxLines: 2,
                decoration: const InputDecoration(labelText: 'Observações', alignLabelWithHint: true),
              ),
            ],
          ),
        ),
        _bottomBar(),
      ]),
    );
  }

  Widget _docTypeSelector() {
    const opts = [('FT', 'Fatura'), ('FR', 'Fatura-Recibo'), ('NC', 'Nota Crédito')];
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Wrap(spacing: 8, children: opts.map((o) {
        final sel = _docType == o.$1;
        return ChoiceChip(
          label: Text(o.$2),
          selected: sel,
          selectedColor: AppColors.blue,
          labelStyle: TextStyle(color: sel ? Colors.white : AppColors.slate500, fontWeight: FontWeight.w600, fontSize: 12.5),
          onSelected: (_) => setState(() => _docType = o.$1),
        );
      }).toList()),
    );
  }

  Widget _clientCard() {
    return InkWell(
      onTap: _pickClient,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.slate200)),
        child: Row(children: [
          const Icon(Icons.person_outline, color: AppColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(_client?.name ?? 'Consumidor Final', style: const TextStyle(fontWeight: FontWeight.w700)),
              Text(_client?.nif ?? 'Sem NIF', style: const TextStyle(color: AppColors.slate500, fontSize: 12)),
            ]),
          ),
          const Icon(Icons.chevron_right, color: AppColors.slate200),
        ]),
      ),
    );
  }

  Widget _lineCard(int idx, DocLine l) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.slate200)),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l.productName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13.5)),
            const SizedBox(height: 2),
            Text('${l.quantity.toStringAsFixed(l.quantity % 1 == 0 ? 0 : 2)} × ${formatMoney(l.unitPrice)}'
                '${l.discountPercent > 0 ? '  -${l.discountPercent.toStringAsFixed(0)}%' : ''}'
                '  ·  IVA ${l.taxRate.toStringAsFixed(0)}%',
                style: const TextStyle(color: AppColors.slate500, fontSize: 11.5)),
          ]),
        ),
        Text('${formatMoney(l.total)} Kz', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue, fontSize: 13)),
        IconButton(icon: const Icon(Icons.edit, size: 18, color: AppColors.slate500), onPressed: () => _editLine(idx)),
        IconButton(icon: const Icon(Icons.close, size: 18, color: AppColors.red), onPressed: () => setState(() => _lines.removeAt(idx))),
      ]),
    );
  }

  Widget _bottomBar() {
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
      decoration: const BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Color(0x14000000), blurRadius: 12, offset: Offset(0, -3))]),
      child: SafeArea(top: false, child: Column(mainAxisSize: MainAxisSize.min, children: [
        _totalRow('Subtotal', _subtotal),
        _totalRow('IVA', _tax),
        const Divider(height: 14),
        _totalRow('Total', _total, bold: true),
        const SizedBox(height: 12),
        SizedBox(
          height: 52, width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: _saving ? null : _save,
            icon: _saving
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.check),
            label: Text('Criar ${_cfg.label}'),
          ),
        ),
      ])),
    );
  }

  Widget _totalRow(String label, double v, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.normal, fontSize: bold ? 16 : 13, color: bold ? Colors.black : AppColors.slate500)),
        Text('${formatMoney(v)} Kz', style: TextStyle(fontWeight: bold ? FontWeight.bold : FontWeight.w600, fontSize: bold ? 16 : 13, color: bold ? AppColors.blue : Colors.black)),
      ]),
    );
  }

  Widget _errorBox(String msg) => Container(
        margin: const EdgeInsets.only(bottom: 14),
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFCA5A5))),
        child: Row(children: [
          const Icon(Icons.error_outline, color: AppColors.red, size: 18),
          const SizedBox(width: 8),
          Expanded(child: Text(msg, style: const TextStyle(color: AppColors.red, fontSize: 13))),
        ]),
      );
}

// ==================== Picker de produto ====================
class _ProductPicker extends StatefulWidget {
  final List<Product> products;
  const _ProductPicker({required this.products});
  @override
  State<_ProductPicker> createState() => _ProductPickerState();
}

class _ProductPickerState extends State<_ProductPicker> {
  String _q = '';
  @override
  Widget build(BuildContext context) {
    final filtered = _q.isEmpty
        ? widget.products
        : widget.products.where((p) => p.name.toLowerCase().contains(_q.toLowerCase()) || (p.sku ?? '').toLowerCase().contains(_q.toLowerCase())).toList();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SizedBox(
        height: MediaQuery.of(context).size.height * 0.7,
        child: Column(children: [
          const SizedBox(height: 10),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.slate200, borderRadius: BorderRadius.circular(2))),
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              autofocus: true,
              decoration: const InputDecoration(hintText: 'Pesquisar artigo…', prefixIcon: Icon(Icons.search)),
              onChanged: (v) => setState(() => _q = v),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.edit_note, color: AppColors.orange),
            title: const Text('Linha manual / artigo livre'),
            onTap: () => Navigator.pop(context, Product(id: -1, name: 'Artigo', price: 0, taxRate: 14, stockQuantity: 0)),
          ),
          const Divider(height: 1),
          Expanded(
            child: filtered.isEmpty
                ? const Center(child: Text('Sem artigos', style: TextStyle(color: AppColors.slate500)))
                : ListView.separated(
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => const Divider(height: 1),
                    itemBuilder: (_, i) {
                      final p = filtered[i];
                      return ListTile(
                        title: Text(p.name),
                        subtitle: Text('${p.sku ?? ''}  ·  Stock: ${p.stockQuantity.toStringAsFixed(0)}'),
                        trailing: Text('${formatMoney(p.price)} Kz', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue)),
                        onTap: () => Navigator.pop(context, p),
                      );
                    },
                  ),
          ),
        ]),
      ),
    );
  }
}

// ==================== Picker de cliente ====================
class _ClientPicker extends StatefulWidget {
  final List<ClientModel> clients;
  const _ClientPicker({required this.clients});
  @override
  State<_ClientPicker> createState() => _ClientPickerState();
}

class _ClientPickerState extends State<_ClientPicker> {
  String _q = '';
  @override
  Widget build(BuildContext context) {
    final filtered = _q.isEmpty
        ? widget.clients
        : widget.clients.where((c) => c.name.toLowerCase().contains(_q.toLowerCase()) || (c.nif ?? '').contains(_q)).toList();
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(children: [
        const SizedBox(height: 10),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.slate200, borderRadius: BorderRadius.circular(2))),
        Padding(
          padding: const EdgeInsets.all(12),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Pesquisar cliente…', prefixIcon: Icon(Icons.search)),
            onChanged: (v) => setState(() => _q = v),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.person_off_outlined, color: AppColors.orange),
          title: const Text('Consumidor Final'),
          onTap: () => Navigator.pop(context, ClientModel(id: -1, name: 'Consumidor Final')),
        ),
        const Divider(height: 1),
        Expanded(
          child: ListView.separated(
            itemCount: filtered.length,
            separatorBuilder: (_, __) => const Divider(height: 1),
            itemBuilder: (_, i) {
              final c = filtered[i];
              return ListTile(
                title: Text(c.name),
                subtitle: Text(c.nif ?? 'Sem NIF'),
                onTap: () => Navigator.pop(context, c),
              );
            },
          ),
        ),
      ]),
    );
  }
}

// ==================== Diálogo de edição de linha ====================
class _LineDialog extends StatefulWidget {
  final DocLine line;
  const _LineDialog({required this.line});
  @override
  State<_LineDialog> createState() => _LineDialogState();
}

class _LineDialogState extends State<_LineDialog> {
  late final _name = TextEditingController(text: widget.line.productName);
  late final _qty = TextEditingController(text: widget.line.quantity.toString());
  late final _price = TextEditingController(text: widget.line.unitPrice.toString());
  late final _tax = TextEditingController(text: widget.line.taxRate.toString());
  late final _disc = TextEditingController(text: widget.line.discountPercent.toString());

  @override
  void dispose() {
    for (final c in [_name, _qty, _price, _tax, _disc]) { c.dispose(); }
    super.dispose();
  }

  double _p(TextEditingController c) => double.tryParse(c.text.replaceAll(',', '.')) ?? 0;

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Editar artigo'),
      content: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          TextField(controller: _name, decoration: const InputDecoration(labelText: 'Descrição')),
          Row(children: [
            Expanded(child: TextField(controller: _qty, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Qtd'))),
            const SizedBox(width: 10),
            Expanded(child: TextField(controller: _price, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Preço'))),
          ]),
          Row(children: [
            Expanded(child: TextField(controller: _tax, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'IVA %'))),
            const SizedBox(width: 10),
            Expanded(child: TextField(controller: _disc, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: const InputDecoration(labelText: 'Desc. %'))),
          ]),
        ]),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancelar')),
        ElevatedButton(
          onPressed: () => Navigator.pop(context, DocLine(
            productId: widget.line.productId,
            productName: _name.text.trim().isEmpty ? 'Artigo' : _name.text.trim(),
            quantity: _p(_qty) <= 0 ? 1 : _p(_qty),
            unitPrice: _p(_price),
            taxRate: _p(_tax),
            discountPercent: _p(_disc),
          )),
          child: const Text('OK'),
        ),
      ],
    );
  }
}
