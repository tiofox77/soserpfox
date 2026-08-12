import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../data/invoicing_api.dart';
import '../../state/app_state.dart';
import 'entity_detail_screen.dart';

/// Dashboard de Faturação — KPIs + contagens + faturas recentes (via API).
class InvoicingDashboardBody extends StatefulWidget {
  const InvoicingDashboardBody({super.key});
  @override
  State<InvoicingDashboardBody> createState() => _InvoicingDashboardBodyState();
}

class _InvoicingDashboardBodyState extends State<InvoicingDashboardBody> {
  final _api = InvoicingApi();
  bool _loading = true;
  String? _error;
  Map<String, dynamic> _stats = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (context.read<AppState>().demoMode) {
      setState(() { _loading = false; _error = 'demo'; });
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      final s = await _api.dashboardStats();
      if (!mounted) return;
      setState(() { _stats = s; _loading = false; });
    } catch (e) {
      if (!mounted) return;
      setState(() { _error = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error == 'demo') {
      return const Center(child: Padding(padding: EdgeInsets.all(32),
          child: Text('Entre com a sua conta para ver as estatísticas reais.',
              textAlign: TextAlign.center, style: TextStyle(color: AppColors.slate500))));
    }
    if (_error != null) {
      return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.cloud_off, size: 48, color: AppColors.slate200),
        const SizedBox(height: 10),
        ElevatedButton.icon(style: ElevatedButton.styleFrom(backgroundColor: AppColors.blue),
            onPressed: _load, icon: const Icon(Icons.refresh, size: 18), label: const Text('Tentar de novo')),
      ]));
    }

    final counts = (_stats['counts'] ?? {}) as Map;
    final recent = (_stats['recent_invoices'] as List? ?? []);

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // KPIs de vendas
          Row(children: [
            _kpi('Total Vendas', _stats['sales_total'], AppColors.blue, Icons.trending_up),
            const SizedBox(width: 10),
            _kpi('Pago', _stats['sales_paid'], AppColors.emerald, Icons.check_circle),
          ]),
          const SizedBox(height: 10),
          _kpiWide('Por receber', _stats['sales_pending'], AppColors.amber, Icons.schedule),
          const SizedBox(height: 18),
          // Contagens
          GridView.count(
            crossAxisCount: 3, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.1,
            children: [
              _countCard('Faturas', counts['sales_invoices'], Icons.receipt_long),
              _countCard('Proformas', counts['proformas'], Icons.description),
              _countCard('Recibos', counts['receipts'], Icons.payments),
              _countCard('Clientes', counts['clients'], Icons.people),
              _countCard('Produtos', counts['products'], Icons.inventory_2),
              _countCard('Fornec.', counts['suppliers'], Icons.local_shipping),
            ],
          ),
          const SizedBox(height: 18),
          const Text('Faturas recentes', style: TextStyle(fontSize: 15, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          if (recent.isEmpty)
            const Padding(padding: EdgeInsets.symmetric(vertical: 16),
                child: Text('Sem faturas ainda.', style: TextStyle(color: AppColors.slate500)))
          else
            ...recent.map((r) => _recentTile(Map<String, dynamic>.from(r))),
        ],
      ),
    );
  }

  Widget _kpi(String label, dynamic v, Color c, IconData icon) => Expanded(
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.slate200)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(icon, color: c, size: 22),
            const SizedBox(height: 8),
            Text('${formatMoney(v is num ? v : 0)} Kz', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: c)),
            Text(label, style: const TextStyle(color: AppColors.slate500, fontSize: 11)),
          ]),
        ),
      );

  Widget _kpiWide(String label, dynamic v, Color c, IconData icon) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(gradient: AppColors.brandGradient, borderRadius: BorderRadius.circular(16)),
        child: Row(children: [
          Icon(icon, color: Colors.white, size: 26),
          const SizedBox(width: 12),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12)),
            Text('${formatMoney(v is num ? v : 0)} Kz', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 20)),
          ]),
        ]),
      );

  Widget _countCard(String label, dynamic v, IconData icon) => Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.slate200)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, color: AppColors.blue, size: 22),
          const SizedBox(height: 6),
          Text('${v ?? 0}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
          Text(label, style: const TextStyle(color: AppColors.slate500, fontSize: 11)),
        ]),
      );

  Widget _recentTile(Map<String, dynamic> r) => Container(
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: AppColors.slate200)),
        child: ListTile(
          leading: const CircleAvatar(backgroundColor: Color(0xFFEFF6FF), child: Icon(Icons.receipt_long, color: AppColors.blue, size: 20)),
          title: Text((r['title'] ?? '').toString(), style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
          subtitle: Text('${r['subtitle'] ?? ''} · ${r['date'] ?? ''}', style: const TextStyle(fontSize: 11)),
          trailing: Text('${formatMoney(r['amount'] is num ? r['amount'] : 0)} Kz',
              style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.blue, fontSize: 13)),
          onTap: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => EntityDetailScreen(area: 'sales-invoices', id: r['id'] as int, title: (r['title'] ?? 'Fatura').toString()))),
        ),
      );
}
