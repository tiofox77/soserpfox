import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../state/app_state.dart';
import 'app_sidebar.dart';
import 'dashboard_screen.dart';
import '../pos/pos_screen.dart';
import '../catalog/catalog_screen.dart';
import '../clients/clients_screen.dart';
import '../modules/module_placeholder_screen.dart';
import '../invoicing/entity_list_screen.dart';
import '../invoicing/invoicing_dashboard.dart';

/// Shell tipo website: sidebar (permanente em ecrã largo, drawer em telemóvel)
/// + conteúdo do módulo selecionado.
class AppShell extends StatefulWidget {
  const AppShell({super.key});
  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final app = context.read<AppState>();
      await app.loadLocal();
      await app.doSync(force: app.products.isEmpty);
    });
  }

  Future<void> _syncNow(AppState app) async {
    await app.doSync(force: true);
    if (!mounted) return;
    final ok = app.demoMode || (app.lastSyncMessage == 'Sincronizado');
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(app.demoMode ? 'Modo demonstração (offline)' : (app.lastSyncMessage ?? 'Sincronização concluída')),
      backgroundColor: ok ? AppColors.emerald : AppColors.red,
      behavior: SnackBarBehavior.floating,
      duration: const Duration(seconds: 3),
    ));
  }

  String _titleFor(AppState app) {
    final r = app.currentRoute;
    if (r.startsWith('list:')) return (app.activeModule?['name'] ?? 'Lista').toString();
    switch (r) {
      case 'pos': return 'POS — Ponto de Venda';
      case 'catalog': return 'Catálogo';
      case 'clients': return 'Clientes';
      case 'invoicing-dashboard': return 'Faturação';
      case 'module': return (app.activeModule?['name'] ?? 'Módulo').toString();
      default: return 'Início';
    }
  }

  Widget _bodyFor(AppState app) {
    final r = app.currentRoute;
    if (r.startsWith('list:')) {
      return EntityListBody(area: r.substring(5), key: ValueKey(r));
    }
    switch (r) {
      case 'pos': return const PosBody();
      case 'catalog': return const CatalogBody();
      case 'clients': return const ClientsBody();
      case 'invoicing-dashboard': return const InvoicingDashboardBody();
      case 'module':
        final m = app.activeModule ?? const {};
        return ModulePlaceholderBody(slug: (m['slug'] ?? '').toString(), name: (m['name'] ?? 'Módulo').toString());
      default: return const DashboardBody();
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final wide = MediaQuery.of(context).size.width >= 900;

    final content = Scaffold(
      backgroundColor: AppColors.slate50,
      appBar: AppBar(
        automaticallyImplyLeading: !wide,
        title: Text(_titleFor(app), style: const TextStyle(fontWeight: FontWeight.bold)),
        actions: [
          IconButton(
            icon: app.syncing
                ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                : const Icon(Icons.sync),
            tooltip: 'Sincronizar',
            onPressed: app.syncing ? null : () => _syncNow(app),
          ),
        ],
      ),
      drawer: wide ? null : const Drawer(child: AppSidebar(inDrawer: true)),
      body: SafeArea(top: false, child: _bodyFor(app)),
    );

    if (wide) {
      return Scaffold(
        body: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch, // estica a sidebar em altura
          children: [
            const SizedBox(width: 270, child: AppSidebar()),
            const VerticalDivider(width: 1),
            Expanded(child: content),
          ],
        ),
      );
    }
    return content;
  }
}
