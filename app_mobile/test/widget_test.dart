import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:soserp_faturacao/main.dart';

void main() {
  testWidgets('App arranca sem erros', (WidgetTester tester) async {
    await tester.pumpWidget(const SosErpApp());
    expect(find.byType(MaterialApp), findsOneWidget);
  });
}
