import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../state/app_state.dart';

/// Clientes (conteúdo, sem Scaffold — vive dentro do AppShell).
class ClientsBody extends StatefulWidget {
  const ClientsBody({super.key});
  @override
  State<ClientsBody> createState() => _ClientsBodyState();
}

class _ClientsBodyState extends State<ClientsBody> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = _search.toLowerCase().trim();
    final items = app.clients
        .where((c) => s.isEmpty || c.name.toLowerCase().contains(s) || (c.nif ?? '').contains(s))
        .toList();

    return Column(children: [
      Padding(
        padding: const EdgeInsets.all(12),
        child: TextField(
          decoration: const InputDecoration(hintText: 'Pesquisar por nome ou NIF…', prefixIcon: Icon(Icons.search)),
          onChanged: (v) => setState(() => _search = v),
        ),
      ),
      Expanded(
        child: ListView.separated(
          padding: const EdgeInsets.all(12),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) {
            final c = items[i];
            final initials = c.name.trim().isEmpty
                ? '?'
                : c.name.trim().split(RegExp(r'\s+')).take(2).map((w) => w[0]).join().toUpperCase();
            final color = c.isCompany ? AppColors.blue : AppColors.orange;
            return Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.slate200)),
              child: Row(children: [
                CircleAvatar(backgroundColor: color, child: Text(initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13))),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(c.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    if (c.nif != null) Text('NIF: ${c.nif}', style: const TextStyle(fontSize: 12, color: AppColors.slate500)),
                  ]),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(20)),
                  child: Text(c.isCompany ? 'Empresa' : 'Singular',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: color)),
                ),
              ]),
            );
          },
        ),
      ),
    ]);
  }
}
