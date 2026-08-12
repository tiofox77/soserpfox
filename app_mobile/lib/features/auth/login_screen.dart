import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../core/theme.dart';
import '../../data/auth_api.dart';
import '../../state/app_state.dart';
import '../home/home_shell.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});
  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> with TickerProviderStateMixin {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _auth = AuthApi();
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  late final AnimationController _entrance;
  late final AnimationController _bg;

  @override
  void initState() {
    super.initState();
    _entrance = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..forward();
    _bg = AnimationController(vsync: this, duration: const Duration(seconds: 8))..repeat(reverse: true);
  }

  @override
  void dispose() {
    _entrance.dispose();
    _bg.dispose();
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_email.text.trim().isEmpty || _password.text.isEmpty) {
      setState(() => _error = 'Preencha email e palavra-passe');
      return;
    }
    setState(() { _loading = true; _error = null; });
    final res = await _auth.login(_email.text.trim(), _password.text);
    if (!mounted) return;
    setState(() => _loading = false);
    if (res.success) {
      Navigator.of(context).pushReplacement(_fadeRoute(const AppShell()));
    } else {
      setState(() => _error = res.error);
    }
  }

  Route _fadeRoute(Widget page) => PageRouteBuilder(
        transitionDuration: const Duration(milliseconds: 500),
        pageBuilder: (_, a, __) => FadeTransition(opacity: a, child: page),
      );

  // Animação de entrada (fade + slide) com atraso
  Widget _enter(double start, Widget child) {
    final anim = CurvedAnimation(parent: _entrance, curve: Interval(start, 1.0, curve: Curves.easeOutCubic));
    return AnimatedBuilder(
      animation: anim,
      builder: (_, c) => Opacity(
        opacity: anim.value,
        child: Transform.translate(offset: Offset(0, (1 - anim.value) * 28), child: c),
      ),
      child: child,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: AnimatedBuilder(
        animation: _bg,
        builder: (context, _) {
          return Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: const [AppColors.blue, AppColors.blueDark, AppColors.orange],
                begin: Alignment(-1, -1 + _bg.value),
                end: Alignment(1, 1 - _bg.value * 0.6),
              ),
            ),
            child: Stack(
              children: [
                // círculos decorativos
                Positioned(top: -60, right: -40, child: _blob(180, Colors.white.withValues(alpha: 0.08))),
                Positioned(bottom: -50, left: -30, child: _blob(150, Colors.white.withValues(alpha: 0.06))),
                SafeArea(
                  child: Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 440),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            // Logótipo oficial num cartão branco
                            _enter(0.0, Container(
                              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 16),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(22),
                                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.22), blurRadius: 24, offset: const Offset(0, 10))],
                              ),
                              child: Image.asset('assets/logo.png', height: 64,
                                  errorBuilder: (_, __, ___) => const Text('SOS ERP',
                                      style: TextStyle(color: AppColors.blue, fontSize: 26, fontWeight: FontWeight.w900))),
                            )),
                            const SizedBox(height: 14),
                            _enter(0.12, const Text('Gestão Empresarial · Angola',
                                style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w500, letterSpacing: 0.3))),
                            const SizedBox(height: 26),
                            // Cartão de login
                            _enter(0.22, _card()),
                            const SizedBox(height: 18),
                            _enter(0.4, const Text('© SOS ERP — Softec Angola',
                                style: TextStyle(color: Colors.white60, fontSize: 11))),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _blob(double s, Color c) =>
      Container(width: s, height: s, decoration: BoxDecoration(color: c, shape: BoxShape.circle));

  Widget _card() {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.18), blurRadius: 30, offset: const Offset(0, 12))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Bem-vindo de volta', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: AppColors.slate900)),
          const SizedBox(height: 4),
          const Text('Entre na sua conta para continuar', style: TextStyle(color: AppColors.slate500, fontSize: 13)),
          const SizedBox(height: 18),
          AnimatedSize(
            duration: const Duration(milliseconds: 250),
            child: _error == null
                ? const SizedBox.shrink()
                : Container(
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 14),
                    padding: const EdgeInsets.all(11),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFFFCA5A5)),
                    ),
                    child: Row(children: [
                      const Icon(Icons.error_outline, color: AppColors.red, size: 18),
                      const SizedBox(width: 8),
                      Expanded(child: Text(_error!, style: const TextStyle(color: AppColors.red, fontSize: 13))),
                    ]),
                  ),
          ),
          TextField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(labelText: 'Email', prefixIcon: Icon(Icons.email_outlined)),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _password,
            obscureText: _obscure,
            decoration: InputDecoration(
              labelText: 'Palavra-passe',
              prefixIcon: const Icon(Icons.lock_outline),
              suffixIcon: IconButton(
                icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                onPressed: () => setState(() => _obscure = !_obscure),
              ),
            ),
            onSubmitted: (_) => _submit(),
          ),
          const SizedBox(height: 20),
          // Botão principal animado
          AnimatedScale(
            scale: _loading ? 0.98 : 1,
            duration: const Duration(milliseconds: 150),
            child: SizedBox(
              height: 54,
              child: ElevatedButton(
                onPressed: _loading ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.orange,
                  disabledBackgroundColor: AppColors.orange.withValues(alpha: 0.6),
                ),
                child: _loading
                    ? const SizedBox(height: 22, width: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white))
                    : const Text('Entrar', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
              ),
            ),
          ),
          const SizedBox(height: 8),
          TextButton.icon(
            onPressed: () {
              context.read<AppState>().loadDemo();
              Navigator.of(context).pushReplacement(_fadeRoute(const AppShell()));
            },
            icon: const Icon(Icons.play_circle_outline, size: 18, color: AppColors.blue),
            label: const Text('Ver demonstração (offline)', style: TextStyle(color: AppColors.blue)),
          ),
        ],
      ),
    );
  }
}
