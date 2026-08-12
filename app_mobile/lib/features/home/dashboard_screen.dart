import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../state/app_state.dart';

/// Início — visão geral tipo website: estado da empresa + atalhos + módulos do plano.
class DashboardBody extends StatelessWidget {
  const DashboardBody({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final companyName = app.company?['name']?.toString() ?? 'A minha empresa';

    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
      children: [
        // Cartão de boas-vindas
        Container(
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: AppColors.brandGradient,
            borderRadius: BorderRadius.circular(20),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(companyName,
                  style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.bold)),
              const SizedBox(height: 10),
              Row(children: [
                _pill(app.shiftOpen ? Icons.lock_open : Icons.warning_amber,
                    app.shiftOpen ? 'Turno aberto' : 'Sem turno',
                    app.shiftOpen ? AppColors.emerald : AppColors.red),
                const SizedBox(width: 8),
                if (app.pendingCount > 0)
                  _pill(Icons.sync_problem, '${app.pendingCount} por sincronizar', AppColors.amber),
              ]),
            ],
          ),
        ),
        const _Section('Faturação'),
        Row(children: [
          _quick(context, Icons.point_of_sale, 'POS', AppColors.emerald, 'pos'),
          _quick(context, Icons.inventory_2, 'Catálogo', AppColors.blue, 'catalog'),
          _quick(context, Icons.people, 'Clientes', AppColors.orange, 'clients'),
        ]),
        const _Section('Módulos do seu plano'),
        GridView.count(
          crossAxisCount: 3,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 0.95,
          children: app.modules.map((m) => _moduleCard(context, m)).toList(),
        ),
      ],
    );
  }

  Widget _pill(IconData i, String t, Color c) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(20)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(i, color: Colors.white, size: 14),
          const SizedBox(width: 5),
          Text(t, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      );

  Widget _quick(BuildContext context, IconData icon, String label, Color color, String route) => Expanded(
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 4),
          child: InkWell(
            onTap: () => context.read<AppState>().navigate(route),
            borderRadius: BorderRadius.circular(16),
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.slate200)),
              child: Column(children: [
                CircleAvatar(radius: 22, backgroundColor: color.withValues(alpha: 0.12), child: Icon(icon, color: color)),
                const SizedBox(height: 8),
                Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 12)),
              ]),
            ),
          ),
        ),
      );

  Widget _moduleCard(BuildContext context, Map<String, dynamic> m) {
    final slug = (m['slug'] ?? '').toString();
    final name = (m['name'] ?? slug).toString();
    final meta = moduleMeta(slug);
    return InkWell(
      onTap: () {
        if (slug == 'invoicing') {
          context.read<AppState>().navigate('pos');
        } else {
          context.read<AppState>().navigate('module', module: m);
        }
      },
      borderRadius: BorderRadius.circular(16),
      child: Container(
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.slate200)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          CircleAvatar(radius: 24, backgroundColor: meta.$2.withValues(alpha: 0.12), child: Icon(meta.$1, color: meta.$2, size: 26)),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(name, textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w600, height: 1.1)),
          ),
        ]),
      ),
    );
  }
}

class _Section extends StatelessWidget {
  final String text;
  const _Section(this.text);
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(2, 20, 2, 10),
        child: Text(text, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.bold, color: AppColors.slate900)),
      );
}

/// (ícone, cor) por slug de módulo — partilhado com a sidebar.
(IconData, Color) moduleMeta(String slug) {
  switch (slug) {
    case 'invoicing': return (Icons.receipt_long, AppColors.emerald);
    case 'treasury': return (Icons.account_balance_wallet, Color(0xFF0EA5E9));
    case 'contabilidade': return (Icons.calculate, Color(0xFF6366F1));
    case 'rh': return (Icons.groups, Color(0xFFEC4899));
    case 'oficina': return (Icons.build, Color(0xFFF59E0B));
    case 'eventos': return (Icons.event, AppColors.orange);
    case 'hotel': return (Icons.hotel, Color(0xFF0891B2));
    case 'salon': return (Icons.spa, Color(0xFFDB2777));
    case 'inventario': return (Icons.inventory, Color(0xFF64748B));
    case 'compras': return (Icons.shopping_cart, Color(0xFF16A34A));
    case 'crm': return (Icons.handshake, Color(0xFF7C3AED));
    case 'projetos': return (Icons.work, Color(0xFF2563EB));
    default: return (Icons.widgets, AppColors.blue);
  }
}
