-- Limpar dados do usuário ID 6
DELETE FROM tenant_module WHERE tenant_id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM subscriptions WHERE tenant_id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM orders WHERE user_id = 6;
DELETE FROM tenants WHERE id IN (SELECT tenant_id FROM tenant_user WHERE user_id = 6);
DELETE FROM tenant_user WHERE user_id = 6;
-- Limpar tenants órfãos (sem usuários)
DELETE FROM tenants WHERE id NOT IN (SELECT DISTINCT tenant_id FROM tenant_user WHERE tenant_id IS NOT NULL);
