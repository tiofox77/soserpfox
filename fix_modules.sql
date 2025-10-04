-- Corrigir slug do módulo no plano
UPDATE plans SET included_modules = '["invoicing"]' WHERE id = 1;

-- Ativar módulo invoicing para o tenant 25
INSERT INTO tenant_module (tenant_id, module_id, is_active, activated_at)
VALUES (25, 1, 1, NOW())
ON DUPLICATE KEY UPDATE is_active = 1, activated_at = NOW();

-- Verificar resultado
SELECT 'Plano corrigido e módulo ativado!' as status;

SELECT t.id, t.name, m.name as module_name, tm.is_active
FROM tenants t
LEFT JOIN tenant_module tm ON t.id = tm.tenant_id
LEFT JOIN modules m ON tm.module_id = m.id
WHERE t.id = 25;
