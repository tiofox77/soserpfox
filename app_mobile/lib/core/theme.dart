import 'package:flutter/material.dart';

/// Paleta da MARCA SOS ERP (logótipo oficial): azul do círculo + laranja da raposa.
class AppColors {
  static const blue = Color(0xFF1B75BB);       // azul "ERP" / círculo
  static const blueDark = Color(0xFF135C95);
  static const orange = Color(0xFFE8741E);     // laranja "SOS" / raposa
  static const orangeDark = Color(0xFFCB6014);
  static const emerald = Color(0xFF059669);
  static const emeraldLight = Color(0xFF10B981);
  static const amber = Color(0xFFF59E0B);
  static const red = Color(0xFFEF4444);
  static const slate50 = Color(0xFFF7FAFC);
  static const slate100 = Color(0xFFF1F5F9);
  static const slate200 = Color(0xFFE2E8F0);
  static const slate500 = Color(0xFF64748B);
  static const slate900 = Color(0xFF0F172A);

  /// Gradiente da marca (azul → laranja) — identidade do logótipo SOS ERP.
  static const brandGradient = LinearGradient(
    colors: [blue, orange],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  static const blueGradient = LinearGradient(
    colors: [blue, blueDark],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
  static const orangeGradient = LinearGradient(
    colors: [orange, orangeDark],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );
  static const emeraldGradient = LinearGradient(
    colors: [emeraldLight, emerald],
    begin: Alignment.centerLeft,
    end: Alignment.centerRight,
  );
}

class AppTheme {
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.blue,
      primary: AppColors.blue,
      secondary: AppColors.orange,
    );
    const smoothTransitions = PageTransitionsTheme(builders: {
      TargetPlatform.android: FadeUpwardsPageTransitionsBuilder(),
      TargetPlatform.iOS: CupertinoPageTransitionsBuilder(),
      TargetPlatform.windows: FadeUpwardsPageTransitionsBuilder(),
      TargetPlatform.macOS: CupertinoPageTransitionsBuilder(),
      TargetPlatform.linux: FadeUpwardsPageTransitionsBuilder(),
    });
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: AppColors.slate50,
      fontFamily: 'Roboto',
      pageTransitionsTheme: smoothTransitions,
      appBarTheme: const AppBarTheme(
        backgroundColor: AppColors.blue,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.slate200, width: 2),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.slate200, width: 2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: AppColors.orange, width: 2),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.orange,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          textStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
        ),
      ),
    );
  }
}

/// Formatação de moeda Kwanza (pt-AO style: 1.234,56 Kz)
String formatMoney(num? v) {
  final value = (v ?? 0).toDouble();
  final parts = value.toStringAsFixed(2).split('.');
  final intPart = parts[0];
  final buf = StringBuffer();
  for (int i = 0; i < intPart.length; i++) {
    if (i > 0 && (intPart.length - i) % 3 == 0) buf.write('.');
    buf.write(intPart[i]);
  }
  return '${buf.toString()},${parts[1]}';
}
