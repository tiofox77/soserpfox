-- Corrigir todos os planos para usar slugs corretos
-- faturacao → invoicing

UPDATE plans 
SET included_modules = '["invoicing", "rh", "contabilidade"]' 
WHERE id = 2;

UPDATE plans 
SET included_modules = '["invoicing", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]' 
WHERE id = 3;

UPDATE plans 
SET included_modules = '["invoicing", "rh", "contabilidade", "oficina", "crm", "inventario", "compras", "projetos"]' 
WHERE id = 4;

-- Verificar
SELECT id, name, included_modules FROM plans;
