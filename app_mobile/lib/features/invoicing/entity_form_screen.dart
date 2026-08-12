import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../../data/invoicing_api.dart';

/// Definição de um campo de formulário.
class FieldDef {
  final String key;
  final String label;
  final bool number;
  final bool required;
  const FieldDef(this.key, this.label, {this.number = false, this.required = false});
}

/// Configuração CRUD por área (campos editáveis + rótulo singular).
const Map<String, ({String label, List<FieldDef> fields})> kEditable = {
  'suppliers': (label: 'Fornecedor', fields: [
    FieldDef('name', 'Nome', required: true),
    FieldDef('nif', 'NIF'),
    FieldDef('email', 'Email'),
    FieldDef('phone', 'Telefone'),
    FieldDef('address', 'Morada'),
  ]),
  'categories': (label: 'Categoria', fields: [
    FieldDef('name', 'Nome', required: true),
    FieldDef('description', 'Descrição'),
  ]),
  'brands': (label: 'Marca', fields: [
    FieldDef('name', 'Nome', required: true),
    FieldDef('description', 'Descrição'),
  ]),
  'warehouses': (label: 'Armazém', fields: [
    FieldDef('name', 'Nome', required: true),
    FieldDef('code', 'Código'),
    FieldDef('address', 'Morada'),
  ]),
  'taxes': (label: 'Imposto', fields: [
    FieldDef('name', 'Nome', required: true),
    FieldDef('code', 'Código'),
    FieldDef('rate', 'Taxa (%)', number: true, required: true),
  ]),
};

class EntityFormScreen extends StatefulWidget {
  final String area;
  final Map<String, dynamic>? record; // null = criar; != null = editar
  const EntityFormScreen({super.key, required this.area, this.record});

  @override
  State<EntityFormScreen> createState() => _EntityFormScreenState();
}

class _EntityFormScreenState extends State<EntityFormScreen> {
  final _api = InvoicingApi();
  final _formKey = GlobalKey<FormState>();
  final Map<String, TextEditingController> _ctrls = {};
  bool _saving = false;
  String? _error;

  ({String label, List<FieldDef> fields}) get _cfg => kEditable[widget.area]!;
  bool get _isEdit => widget.record != null;

  @override
  void initState() {
    super.initState();
    for (final f in _cfg.fields) {
      _ctrls[f.key] = TextEditingController(text: widget.record?[f.key]?.toString() ?? '');
    }
  }

  @override
  void dispose() {
    for (final c in _ctrls.values) { c.dispose(); }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() { _saving = true; _error = null; });
    final data = <String, dynamic>{};
    for (final f in _cfg.fields) {
      final v = _ctrls[f.key]!.text.trim();
      data[f.key] = f.number ? (double.tryParse(v.replaceAll(',', '.')) ?? 0) : v;
    }
    try {
      if (_isEdit) {
        await _api.updateItem(widget.area, widget.record!['id'] as int, data);
      } else {
        await _api.create(widget.area, data);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) setState(() { _error = e.toString(); _saving = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = '${_isEdit ? 'Editar' : 'Novo'} ${_cfg.label}';
    return Scaffold(
      appBar: AppBar(title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold))),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null)
              Container(
                margin: const EdgeInsets.only(bottom: 14),
                padding: const EdgeInsets.all(11),
                decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFFCA5A5))),
                child: Row(children: [
                  const Icon(Icons.error_outline, color: AppColors.red, size: 18),
                  const SizedBox(width: 8),
                  Expanded(child: Text(_error!, style: const TextStyle(color: AppColors.red, fontSize: 13))),
                ]),
              ),
            ..._cfg.fields.map((f) => Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: TextFormField(
                    controller: _ctrls[f.key],
                    keyboardType: f.number ? const TextInputType.numberWithOptions(decimal: true) : null,
                    decoration: InputDecoration(labelText: f.label + (f.required ? ' *' : '')),
                    validator: f.required ? (v) => (v == null || v.trim().isEmpty) ? 'Obrigatório' : null : null,
                  ),
                )),
            const SizedBox(height: 6),
            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.save),
                label: Text(_isEdit ? 'Guardar alterações' : 'Criar'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
