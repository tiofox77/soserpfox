import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../core/menu.dart';
import '../../data/auth_api.dart';
import '../../state/app_state.dart';
import '../auth/login_screen.dart';
import 'dashboard_screen.dart' show moduleMeta;

/// Sidebar tipo website: logótipo SOS ERP + empresa + módulos do plano com
/// submenus expansíveis (espelha o sidebar do site).
class AppSidebar extends StatelessWidget {
  final bool inDrawer;
  const AppSidebar({super.key, this.inDrawer = false});

  void _go(BuildContext context, String route, {Map<String, dynamic>? module}) {
    context.read<AppState>().navigate(route, module: module);
    if (inDrawer) Navigator.of(context).maybePop();
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final company = app.company?['name']?.toString() ?? 'A minha empresa';

    return Container(
      color: AppColors.blueDark,
      child: Column(children: [
        // Cabeçalho de marca
        Container(
          padding: const EdgeInsets.fromLTRB(16, 18, 16, 14),
          decoration: const BoxDecoration(gradient: AppColors.brandGradient),
          child: SafeArea(
            bottom: false,
            child: Row(children: [
              Container(
                width: 42, height: 42, padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(11)),
                child: Image.asset('assets/logo.png', errorBuilder: (_, __, ___) => const Icon(Icons.bolt, color: AppColors.blue)),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('SOS ERP', style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold)),
                  Text(company, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white70, fontSize: 11)),
                ]),
              ),
            ]),
          ),
        ),
        Expanded(
          child: Theme(
            data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
            child: ListView(
              padding: const EdgeInsets.only(bottom: 8),
              children: [
                _simpleItem(context, Icons.home, 'Início', app.currentRoute == 'home', () => _go(context, 'home')),
                ...app.modules.map((m) => _moduleTile(context, app, m)),
              ],
            ),
          ),
        ),
        const Divider(color: Colors.white24, height: 1),
        _simpleItem(context, Icons.logout, 'Sair', false, () async {
          await AuthApi().logout();
          if (context.mounted) {
            Navigator.of(context, rootNavigator: true).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const LoginScreen()), (r) => false);
          }
        }, danger: true),
        const SizedBox(height: 6),
      ]),
    );
  }

  Widget _moduleTile(BuildContext context, AppState app, Map<String, dynamic> m) {
    final slug = (m['slug'] ?? '').toString();
    final name = (m['name'] ?? slug).toString();
    final meta = moduleMeta(slug);
    final entries = kModuleMenus[slug];

    // Módulo sem submenus definidos → item simples → placeholder
    if (entries == null || entries.isEmpty) {
      return _simpleItem(context, meta.$1, name, app.currentRoute == 'module' && app.activeModule?['slug'] == slug,
          () => _go(context, 'module', module: m));
    }

    return ExpansionTile(
      leading: Icon(meta.$1, color: meta.$2, size: 20),
      title: Text(name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14)),
      iconColor: Colors.white70,
      collapsedIconColor: Colors.white70,
      childrenPadding: const EdgeInsets.only(left: 8),
      children: entries.map((e) {
        return ListTile(
          dense: true,
          contentPadding: const EdgeInsets.only(left: 28, right: 12),
          leading: Icon(e.icon, color: Colors.white60, size: 18),
          title: Text(e.label, style: const TextStyle(color: Colors.white70, fontSize: 13)),
          onTap: () {
            if (e.route != null && e.route!.startsWith('list:')) {
              _go(context, e.route!, module: {'name': e.label}); // título = nome do item
            } else if (e.route != null) {
              _go(context, e.route!);
            } else {
              _go(context, 'module', module: {'slug': slug, 'name': '$name · ${e.label}'});
            }
          },
        );
      }).toList(),
    );
  }

  Widget _simpleItem(BuildContext context, IconData icon, String label, bool active, VoidCallback onTap, {bool danger = false}) {
    final color = danger ? const Color(0xFFFCA5A5) : Colors.white;
    return Material(
      color: active ? Colors.white.withValues(alpha: 0.12) : Colors.transparent,
      child: ListTile(
        dense: true,
        leading: Icon(icon, color: color, size: 20),
        title: Text(label, style: TextStyle(color: color, fontWeight: active ? FontWeight.bold : FontWeight.w500, fontSize: 14)),
        onTap: onTap,
        shape: active ? const Border(left: BorderSide(color: AppColors.amber, width: 4)) : null,
      ),
    );
  }
}
