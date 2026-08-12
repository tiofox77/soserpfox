import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../state/app_state.dart';

/// Catálogo (conteúdo, sem Scaffold — vive dentro do AppShell).
class CatalogBody extends StatefulWidget {
  const CatalogBody({super.key});
  @override
  State<CatalogBody> createState() => _CatalogBodyState();
}

class _CatalogBodyState extends State<CatalogBody> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final s = _search.toLowerCase().trim();
    final items = app.products
        .where((p) => s.isEmpty || p.name.toLowerCase().contains(s) || (p.sku ?? '').toLowerCase().contains(s))
        .toList();

    return Column(children: [
      Padding(
        padding: const EdgeInsets.all(12),
        child: TextField(
          decoration: const InputDecoration(hintText: 'Pesquisar produto…', prefixIcon: Icon(Icons.search)),
          onChanged: (v) => setState(() => _search = v),
        ),
      ),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Align(
          alignment: Alignment.centerLeft,
          child: Text('${items.length} produtos', style: const TextStyle(color: AppColors.slate500, fontSize: 12)),
        ),
      ),
      Expanded(
        child: ListView.separated(
          padding: const EdgeInsets.all(12),
          itemCount: items.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) {
            final p = items[i];
            return Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14), border: Border.all(color: AppColors.slate200)),
              child: Row(children: [
                CircleAvatar(backgroundColor: const Color(0xFFEFF6FF),
                    child: Icon(p.isService ? Icons.room_service : Icons.inventory_2, color: AppColors.blue)),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(p.name, style: const TextStyle(fontWeight: FontWeight.w600)),
                    if (p.sku != null) Text(p.sku!, style: const TextStyle(fontSize: 11, color: AppColors.slate500)),
                    Text('${p.category ?? '—'} · Stock ${p.stockQuantity.toInt()}', style: const TextStyle(fontSize: 11, color: AppColors.slate500)),
                  ]),
                ),
                Text('${formatMoney(p.price)} Kz', style: const TextStyle(color: AppColors.blue, fontWeight: FontWeight.bold)),
              ]),
            );
          },
        ),
      ),
    ]);
  }
}
