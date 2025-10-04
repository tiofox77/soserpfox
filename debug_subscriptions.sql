-- Comparar subscriptions dos dois usuários

SELECT 
    'admin@faturacao.ao' as usuario,
    u.id as user_id,
    t.id as tenant_id,
    t.name as tenant_name,
    s.id as subscription_id,
    s.status,
    s.billing_cycle,
    s.amount,
    s.trial_ends_at,
    s.current_period_start,
    s.current_period_end,
    s.cancelled_at,
    s.ends_at,
    p.name as plan_name,
    (SELECT COUNT(*) FROM tenant_module tm WHERE tm.tenant_id = t.id AND tm.is_active = 1) as active_modules
FROM users u
LEFT JOIN tenant_user tu ON u.id = tu.user_id
LEFT JOIN tenants t ON tu.tenant_id = t.id
LEFT JOIN subscriptions s ON t.id = s.tenant_id
LEFT JOIN plans p ON s.plan_id = p.id
WHERE u.email = 'admin@faturacao.ao'

UNION ALL

SELECT 
    'carlosfox1782@gmail.com' as usuario,
    u.id as user_id,
    t.id as tenant_id,
    t.name as tenant_name,
    s.id as subscription_id,
    s.status,
    s.billing_cycle,
    s.amount,
    s.trial_ends_at,
    s.current_period_start,
    s.current_period_end,
    s.cancelled_at,
    s.ends_at,
    p.name as plan_name,
    (SELECT COUNT(*) FROM tenant_module tm WHERE tm.tenant_id = t.id AND tm.is_active = 1) as active_modules
FROM users u
LEFT JOIN tenant_user tu ON u.id = tu.user_id
LEFT JOIN tenants t ON tu.tenant_id = t.id
LEFT JOIN subscriptions s ON t.id = s.tenant_id
LEFT JOIN plans p ON s.plan_id = p.id
WHERE u.email = 'carlosfox1782@gmail.com';
