import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../data/invoicing_api.dart';

/// Detalhe de um documento de faturação (cabeçalho + itens + totais).
class EntityDetailScreen extends StatefulWidget {
  final String area;
  final int id;
  final String title;
  const EntityDetailScreen({super.key, required this.area, required this.id, required this.title});

  @override
  State<EntityDetailScreen> createState() => _EntityDetailScreenState();
}

class _EntityDetailScreenState extends State<EntityDetailScreen> {
  final _api = InvoicingApi();
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _record = {};
  List<dynamic> _items = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d = await _api.detail(widget.area, widget.id);
      if (!mounted) return;
      setState(() {
        _record = Map<String, dynamic>.from(d['record'] ?? {});
        _items = (d['items'] as List? ?? []);
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.bold))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text('Erro: $_error', style: const TextStyle(color: AppColors.red)))
              : _content(),
    );
  }

  Widget _content() {
    final r = _record;
    final client = r['_client_name'] ?? r['_supplier_name'];
    final total = r['total'] ?? r['amount'];
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Cabeçalho
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(gradient: AppColors.brandGradient, borderRadius: BorderRadius.circular(18)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(widget.title, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold)),
            if (client != null) Padding(padding: const EdgeInsets.only(top: 4),
                child: Text(client.toString(), style: const TextStyle(color: Colors.white70))),
            if (total != null) Padding(padding: const EdgeInsets.only(top: 10),
                child: Text('${formatMoney(total is num ? total : double.tryParse('$total') ?? 0)} Kz',
                    style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w900))),
          ]),
        ),
        const SizedBox(height: 16),
        // Itens
        if (_items.isNotEmpty) ...[
          const Text('Itens', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          ..._items.map((it) {
            final i = Map<String, dynamic>.from(it);
            return Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.slate200)),
              child: Row(children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text((i['product_name'] ?? 'Item').toString(), style: const TextStyle(fontWeight: FontWeight.w600)),
                  Text('${i['quantity']} × ${formatMoney(i['unit_price'] is num ? i['unit_price'] : 0)} · IVA ${(i['tax_rate'] ?? 0)}%',
                      style: const TextStyle(fontSize: 12, color: AppColors.slate500)),
                ])),
                Text('${formatMoney(i['total'] is num ? i['total'] : 0)} Kz', style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue)),
              ]),
            );
          }),
          const SizedBox(height: 16),
        ],
        // Campos do registo
        const Text('Detalhes', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.slate200)),
          child: Column(children: _fields(r)),
        ),
      ],
    );
  }

  List<Widget> _fields(Map<String, dynamic> r) {
    const labels = {
      'status': 'Estado', 'invoice_date': 'Data', 'proforma_date': 'Data', 'due_date': 'Vencimento',
      'subtotal': 'Subtotal', 'tax_amount': 'IVA', 'tax_payable': 'IVA', 'discount_amount': 'Desconto',
      'total': 'Total', 'amount': 'Valor', 'notes': 'Notas', 'invoice_number': 'Número',
      'nif': 'NIF', 'email': 'Email', 'phone': 'Telefone', 'address': 'Morada',
    };
    final out = <Widget>[];
    labels.forEach((key, label) {
      if (r.containsKey(key) && r[key] != null && r[key].toString().isNotEmpty) {
        out.add(_fieldRow(label, r[key].toString()));
      }
    });
    if (out.isEmpty) out.add(_fieldRow('ID', (r['id'] ?? '-').toString()));
    return out;
  }

  Widget _fieldRow(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 11),
        child: Row(children: [
          Expanded(child: Text(label, style: const TextStyle(color: AppColors.slate500, fontSize: 13))),
          Flexible(child: Text(value, textAlign: TextAlign.right, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13))),
        ]),
      );
}
