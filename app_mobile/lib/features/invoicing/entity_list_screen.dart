import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../data/invoicing_api.dart';
import '../../state/app_state.dart';
import 'entity_detail_screen.dart';
import 'entity_form_screen.dart';
import 'document_form_screen.dart';

const _detailAreas = {
  'sales-invoices', 'purchase-invoices', 'sales-proformas',
  'purchase-proformas', 'credit-notes', 'debit-notes', 'advances', 'receipts',
};

/// Ecrã de lista genérico para qualquer área de faturação (ligado à API).
class EntityListBody extends StatefulWidget {
  final String area;
  const EntityListBody({super.key, required this.area});

  @override
  State<EntityListBody> createState() => _EntityListBodyState();
}

class _EntityListBodyState extends State<EntityListBody> {
  final _api = InvoicingApi();
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  String _search = '';

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (context.read<AppState>().demoMode) {
      setState(() { _loading = false; _items = []; _error = 'demo'; });
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final data = await _api.list(widget.area);
      if (!mounted) return;
      setState(() { _items = data; _loading = false; });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  List<Map<String, dynamic>> get _filtered {
    final s = _search.toLowerCase().trim();
    if (s.isEmpty) return _items;
    return _items.where((m) =>
        (m['title'] ?? '').toString().toLowerCase().contains(s) ||
        (m['subtitle'] ?? '').toString().toLowerCase().contains(s)).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());

    if (_error == 'demo') {
      return _info(Icons.play_circle_outline, 'Modo demonstração',
          'Entre com a sua conta para ver os dados reais desta área.');
    }
    if (_error != null) {
      return _info(Icons.cloud_off, 'Sem ligação', _error!, retry: true);
    }

    final editable = kEditable.containsKey(widget.area);
    final isDoc = kDocTypes.containsKey(widget.area);
    final hasFab = editable || isDoc;
    final column = Column(children: [
      Padding(
        padding: const EdgeInsets.all(12),
        child: TextField(
          decoration: const InputDecoration(hintText: 'Pesquisar…', prefixIcon: Icon(Icons.search)),
          onChanged: (v) => setState(() => _search = v),
        ),
      ),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Align(alignment: Alignment.centerLeft,
            child: Text('${_filtered.length} registo(s)', style: const TextStyle(color: AppColors.slate500, fontSize: 12))),
      ),
      Expanded(
        child: _filtered.isEmpty
            ? _info(Icons.inbox_outlined, 'Sem registos', 'Ainda não há nada nesta área.')
            : RefreshIndicator(
                onRefresh: _load,
                child: ListView.separated(
                  padding: EdgeInsets.fromLTRB(12, 12, 12, hasFab ? 84 : 12),
                  itemCount: _filtered.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (_, i) => editable ? _dismissible(_filtered[i]) : _card(_filtered[i]),
                ),
              ),
      ),
    ]);

    if (!hasFab) return column;
    return Stack(children: [
      column,
      Positioned(
        right: 16, bottom: 16,
        child: FloatingActionButton.extended(
          backgroundColor: AppColors.orange,
          onPressed: isDoc ? _openDocForm : () => _openForm(),
          icon: const Icon(Icons.add),
          label: Text(isDoc ? 'Nova ${kDocTypes[widget.area]!.label}' : 'Novo ${kEditable[widget.area]!.label}'),
        ),
      ),
    ]);
  }

  Future<void> _openDocForm() async {
    final ok = await Navigator.push<bool>(context,
        MaterialPageRoute(builder: (_) => DocumentFormScreen(area: widget.area)));
    if (ok == true) _load();
  }

  Future<void> _openForm([Map<String, dynamic>? record]) async {
    final ok = await Navigator.push<bool>(context,
        MaterialPageRoute(builder: (_) => EntityFormScreen(area: widget.area, record: record)));
    if (ok == true) {
      _load();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text(record == null ? 'Criado com sucesso' : 'Atualizado com sucesso'),
          backgroundColor: AppColors.emerald, behavior: SnackBarBehavior.floating));
      }
    }
  }

  Widget _dismissible(Map<String, dynamic> m) {
    return Dismissible(
      key: ValueKey('${widget.area}_${m['id']}'),
      direction: DismissDirection.endToStart,
      confirmDismiss: (_) async {
        return await showDialog<bool>(context: context, builder: (_) => AlertDialog(
          title: const Text('Eliminar?'),
          content: Text('Eliminar "${m['title']}"?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancelar')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Eliminar', style: TextStyle(color: AppColors.red))),
          ],
        )) ?? false;
      },
      onDismissed: (_) async {
        try {
          await _api.deleteItem(widget.area, m['id'] as int);
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Eliminado'), behavior: SnackBarBehavior.floating));
        } catch (e) {
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: AppColors.red));
          _load();
        }
      },
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        decoration: BoxDecoration(color: AppColors.red, borderRadius: BorderRadius.circular(14)),
        child: const Icon(Icons.delete, color: Colors.white),
      ),
      child: _card(m),
    );
  }

  Widget _card(Map<String, dynamic> m) {
    final amount = m['amount'];
    final isRate = m['is_rate'] == true;
    final status = (m['status'] ?? '').toString();
    final canDetail = _detailAreas.contains(widget.area) && m['id'] != null;
    final inner = Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.slate200)),
      child: Row(children: [
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text((m['title'] ?? '').toString(), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            if ((m['subtitle'] ?? '').toString().isNotEmpty)
              Padding(padding: const EdgeInsets.only(top: 2),
                  child: Text(m['subtitle'].toString(), maxLines: 1, overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: AppColors.slate500, fontSize: 12.5))),
            if ((m['date'] ?? '').toString().isNotEmpty)
              Padding(padding: const EdgeInsets.only(top: 2),
                  child: Text(m['date'].toString(), style: const TextStyle(color: AppColors.slate500, fontSize: 11))),
          ]),
        ),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          if (amount != null)
            Text(isRate ? '${(amount as num).toStringAsFixed(0)}%' : '${formatMoney(amount as num)} Kz',
                style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue, fontSize: 14)),
          if (status.isNotEmpty)
            Container(
              margin: const EdgeInsets.only(top: 4),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(color: _statusColor(status).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
              child: Text(_statusLabel(status),
                  style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: _statusColor(status))),
            ),
        ]),
        if (canDetail) const Padding(padding: EdgeInsets.only(left: 6), child: Icon(Icons.chevron_right, color: AppColors.slate200)),
      ]),
    );

    if (!canDetail) return inner;
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: () => Navigator.push(context, MaterialPageRoute(
          builder: (_) => EntityDetailScreen(area: widget.area, id: m['id'] as int, title: (m['title'] ?? 'Documento').toString()))),
      child: inner,
    );
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'paid': case 'completed': case 'active': case 'accepted': return AppColors.emerald;
      case 'pending': case 'draft': case 'sent': return AppColors.amber;
      case 'cancelled': case 'rejected': case 'overdue': return AppColors.red;
      default: return AppColors.slate500;
    }
  }

  String _statusLabel(String s) {
    const map = {
      'paid': 'Pago', 'pending': 'Pendente', 'draft': 'Rascunho', 'sent': 'Enviado',
      'partial': 'Parcial', 'overdue': 'Vencido', 'cancelled': 'Cancelado',
      'completed': 'Concluído', 'active': 'Ativo', 'accepted': 'Aceite', 'rejected': 'Rejeitado',
    };
    return map[s] ?? s;
  }

  Widget _info(IconData icon, String title, String msg, {bool retry = false}) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 56, color: AppColors.slate200),
          const SizedBox(height: 12),
          Text(title, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold)),
          const SizedBox(height: 6),
          Text(msg, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.slate500), maxLines: 3, overflow: TextOverflow.ellipsis),
          if (retry) ...[
            const SizedBox(height: 14),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(backgroundColor: AppColors.blue),
              onPressed: _load, icon: const Icon(Icons.refresh, size: 18), label: const Text('Tentar de novo')),
          ],
        ]),
      ),
    );
  }
}
