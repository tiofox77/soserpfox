-- Limpeza completa do usuário ID 6
SET FOREIGN_KEY_CHECKS=0;

-- Buscar e deletar tudo relacionado
DELETE FROM tenant_module WHERE tenant_id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM subscriptions WHERE tenant_id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM invoices WHERE tenant_id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM orders WHERE user_id = 6;

-- Deletar tenants e vínculos
DELETE FROM tenants WHERE id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM tenant_user WHERE user_id = 6;

-- Limpar tenants órfãos
DELETE FROM tenants WHERE id NOT IN (SELECT DISTINCT tenant_id FROM tenant_user WHERE tenant_id IS NOT NULL);

SET FOREIGN_KEY_CHECKS=1;

SELECT '✅ LIMPEZA COMPLETA CONCLUÍDA!' as status;
