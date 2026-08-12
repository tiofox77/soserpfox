import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/theme.dart';
import 'core/db_init.dart';
import 'data/auth_api.dart';
import 'state/app_state.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_shell.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  initDesktopDb(); // ativa o sqflite FFI em Windows/desktop
  runApp(const SosErpApp());
}

class SosErpApp extends StatelessWidget {
  const SosErpApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AppState(),
      child: MaterialApp(
        title: 'SOS ERP — Faturação',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        home: const _Bootstrap(),
      ),
    );
  }
}

/// Decide o ecrã inicial conforme exista token guardado.
class _Bootstrap extends StatelessWidget {
  const _Bootstrap();

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<bool>(
      future: AuthApi().isLoggedIn(),
      builder: (_, snap) {
        if (!snap.hasData) {
          return const Scaffold(body: Center(child: CircularProgressIndicator()));
        }
        return snap.data! ? const AppShell() : const LoginScreen();
      },
    );
  }
}
