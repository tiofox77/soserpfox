import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../home/dashboard_screen.dart' show moduleMeta;

/// Conteúdo genérico para módulos do plano ainda sem versão nativa completa.
class ModulePlaceholderBody extends StatelessWidget {
  final String slug;
  final String name;
  const ModulePlaceholderBody({super.key, required this.slug, required this.name});

  @override
  Widget build(BuildContext context) {
    final meta = moduleMeta(slug);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 88, height: 88,
              decoration: BoxDecoration(color: meta.$2.withValues(alpha: 0.12), shape: BoxShape.circle),
              child: Icon(meta.$1, size: 44, color: meta.$2),
            ),
            const SizedBox(height: 18),
            Text(name, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            const Text(
              'Este módulo faz parte do seu plano. A versão nativa na app está a chegar — '
              'por agora está totalmente disponível no portal web.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.slate500, height: 1.4),
            ),
            const SizedBox(height: 20),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(color: AppColors.amber.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
              child: const Text('Em breve na app',
                  style: TextStyle(color: Color(0xFFB45309), fontWeight: FontWeight.bold, fontSize: 12)),
            ),
          ],
        ),
      ),
    );
  }
}
