#!/bin/bash

# ==============================================================================
# SOSERP - Script de Deploy para cPanel/Produção
# ==============================================================================
# Este script é executado automaticamente após git pull no servidor
# Pode ser chamado via:
#   - GitHub Actions
#   - Webhook do cPanel
#   - SSH manual
# ==============================================================================

set -e  # Parar em caso de erro

echo "🚀 Iniciando deploy do SOSERP..."
echo "📅 $(date)"
echo "=============================================="

# Diretório da aplicação (ajustar conforme necessário)
APP_DIR="${APP_DIR:-$(pwd)}"
cd "$APP_DIR"

echo "📂 Diretório: $APP_DIR"

# 1. Ativar modo de manutenção
echo ""
echo "🔧 Ativando modo de manutenção..."
php artisan down --render="errors::503" --retry=60 || true

# 2. Atualizar código do Git
echo ""
echo "📥 Atualizando código do repositório..."
git fetch origin
git reset --hard origin/main

# 3. Instalar/atualizar dependências do Composer
echo ""
echo "📦 Instalando dependências do Composer..."
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 4. Executar migrations
echo ""
echo "🗄️ Executando migrations..."
php artisan migrate --force

# 5. Executar seeders (apenas os de módulos para garantir que novos módulos são criados)
echo ""
echo "🌱 Atualizando módulos..."
php artisan db:seed --class=ModuleSeeder --force

# 6. Limpar e otimizar cache
echo ""
echo "🧹 Limpando e otimizando cache..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "⚡ Otimizando para produção..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Reiniciar filas (se aplicável)
echo ""
echo "🔄 Reiniciando workers de fila..."
php artisan queue:restart || true

# 8. Desativar modo de manutenção
echo ""
echo "✅ Desativando modo de manutenção..."
php artisan up

echo ""
echo "=============================================="
echo "🎉 Deploy concluído com sucesso!"
echo "📅 $(date)"
echo "=============================================="
