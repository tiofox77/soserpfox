# 🚀 SOS ERP - Sistema de Gestão Empresarial

## 📋 Sistema Modular Integrado

O SOS ERP é um sistema **modular** onde cada módulo funciona de forma independente, mas **certas áreas estão sempre integradas automaticamente** para manter consistência e automatizar processos.

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11.x-red?logo=laravel" alt="Laravel">
  <img src="https://img.shields.io/badge/Livewire-3.x-purple?logo=livewire" alt="Livewire">
  <img src="https://img.shields.io/badge/PHP-8.3-blue?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/TailwindCSS-3.x-cyan?logo=tailwindcss" alt="TailwindCSS">
  <img src="https://img.shields.io/badge/Alpine.js-3.x-teal?logo=alpinedotjs" alt="Alpine.js">
</p>

Sistema ERP multi-tenant completo desenvolvido em Laravel 11 + Livewire V3, com foco em gestão de faturação, clientes, fornecedores, produtos e integração com AGT Angola.

---

## 📋 Índice

- [Características](#-características)
- [Tecnologias](#-tecnologias)
- [Módulos Implementados](#-módulos-implementados)
- [Integrações Entre Módulos](#-integrações-entre-módulos) ⭐ IMPORTANTE
- [Instalação](#-instalação)
- [Atualização do Sistema](#-atualização-do-sistema)
- [Estrutura do Projeto](#-estrutura-do-projeto)
- [Funcionalidades Detalhadas](#-funcionalidades-detalhadas)
- [Roadmap](#-roadmap)
- [Contribuição](#-contribuição)
- [Licença](#-licença)

---

## ✨ Características

- 🏢 **Multi-tenant** - Sistema isolado por tenant
- 🎨 **UI/UX Moderna** - Interface profissional com TailwindCSS
- ⚡ **SPA-like** - Sem reloads com Livewire V3
- 📱 **Responsivo** - Mobile-first design
- 🔐 **Seguro** - Autenticação e autorização robustas
- 🇦🇴 **Compatível AGT** - Sistema de faturação para Angola
- 📤 **Upload Inteligente** - Sistema organizado por entidade
- 🗑️ **Modal de Confirmação** - Delete seguro com confirmação visual
- 🎯 **Menu Hierárquico** - Navegação colapsável por módulos

---

## 🛠️ Tecnologias

### Backend
- **Laravel 11** - Framework PHP
- **Livewire V3** - Componentes reativos
- **MySQL** - Banco de dados
- **Laravel Sanctum** - Autenticação API

### Frontend
- **TailwindCSS 3.x** - Framework CSS
- **Alpine.js 3.x** - Interatividade JavaScript
- **Font Awesome 6** - Ícones
- **Toastr** - Notificações

### Infraestrutura
- **Laravel Sail / Laragon** - Ambiente de desenvolvimento
- **Storage** - Sistema de arquivos organizado
- **Migrations** - Versionamento de banco de dados
- **Seeders** - Dados iniciais

---

## 📦 Módulos Implementados

### 🔐 Super Admin
- ✅ Dashboard
- ✅ Gestão de Tenants
- ✅ Gestão de Módulos
- ✅ Planos e Billing

### 💰 Módulo de Faturação (Invoicing)

#### 👥 Clientes
- ✅ CRUD completo
- ✅ Upload de logo (organizado por ID)
- ✅ País select (8 países)
- ✅ Província dinâmica (18 províncias de Angola)
- ✅ Filtros avançados (tipo, cidade, data)
- ✅ Stats cards com métricas
- ✅ Paginação customizável

#### 🚚 Fornecedores
- ✅ CRUD completo
- ✅ Upload de logo organizado
- ✅ Mesma estrutura de clientes
- ✅ Gestão de contatos e localização

#### 📦 Produtos
- ✅ CRUD completo
- ✅ Upload de imagem destaque
- ✅ Galeria de múltiplas imagens
- ✅ Stock mínimo e máximo
- ✅ Sistema de IVA Angola (14%, 7%, 5%)
- ✅ Motivos de isenção AGT (M01-M99)
- ✅ Relacionamento com Categorias, Marcas e Fornecedores
- ✅ Cálculo automático de preços com IVA

#### 🏷️ Categorias
- ✅ CRUD completo
- ✅ Sistema hierárquico (pai/filho)
- ✅ Ícones Font Awesome customizáveis
- ✅ Color picker para cores
- ✅ Ordenação customizável
- ✅ Slug auto-gerado

#### 🔖 Marcas
- ✅ CRUD completo
- ✅ Logo e website
- ✅ Ordenação customizável
- ✅ Status ativo/inativo

#### 📃 Faturas
- ✅ CRUD completo
- ✅ Sistema de numeração automática
- ✅ Múltiplos status (draft, sent, paid, cancelled)
- ✅ Filtros avançados
- ✅ Relacionamento com clientes e produtos

#### 💵 Taxas de IVA
- ✅ Gestão de taxas de IVA
- ✅ Taxas padrão de Angola (14%, 7%, 5%)
- ✅ Sistema extensível para outras taxas
- ✅ Seeder automático por tenant

---

## 🔗 Integrações Entre Módulos

⚠️ **IMPORTANTE:** O SOS ERP é modular, mas **certas áreas estão sempre integradas automaticamente**.

### Integrações Implementadas ✅

#### **1. POS → Faturação → Treasury**
```
Venda POS → Cria Fatura (FR) → Cria Transação Treasury
```
- Automático e atômico (DB Transaction)
- Fatura vinculada à transação
- Saldo do caixa atualizado

#### **2. Treasury → Faturação (Crédito)**
```
Creditar Transação → Cria Nota de Crédito → Atualiza Fatura
```
- NC criada automaticamente
- Status da fatura → 'credited'
- Rastreabilidade completa

### Documentação Completa

📚 **Leia antes de modificar integrações:**
- [`MODULE-INTEGRATIONS.md`](MODULE-INTEGRATIONS.md) - Todas as integrações do sistema
- [`INTEGRATION-RULES.md`](INTEGRATION-RULES.md) - Regras obrigatórias

### Princípios

- ✅ **Automático** - Integrações acontecem automaticamente
- ✅ **Atômico** - Usa transações DB (tudo ou nada)
- ✅ **Vinculado** - Registros sempre ligados por foreign keys
- ✅ **Rastreável** - Histórico completo de ações

---

## 🔄 Atualização do Sistema

### Comando de Atualização Inteligente

```bash
php artisan system:update
```

**Menu Interativo:**
- 🚀 Automático - Executa tudo (recomendado)
- ✋ Interativo - Pergunta antes de cada seeder
- ❌ Cancelar

**Para CI/CD:**
```bash
php artisan system:update --force
```

**Documentação:**
- [`SYSTEM-UPDATE.md`](SYSTEM-UPDATE.md) - Documentação completa
- [`UPDATE-QUICK-START.md`](UPDATE-QUICK-START.md) - Guia rápido

---

## 🚀 Instalação

### Requisitos
- PHP 8.3+
- Composer
- Node.js 18+
- MySQL 8.0+

### Passos

1. **Clone o repositório**
```bash
git clone https://github.com/seu-usuario/soserp.git
cd soserp
```

2. **Instale as dependências**
```bash
composer install
npm install
```

3. **Configure o ambiente**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure o banco de dados**
Edite o arquivo `.env` com suas credenciais:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soserp
DB_USERNAME=root
DB_PASSWORD=
```

5. **Execute as migrations**
```bash
php artisan migrate
```

6. **Execute os seeders**
```bash
php artisan db:seed --class=TaxRateSeeder
```

7. **Crie o link simbólico para storage**
```bash
php artisan storage:link
```

8. **Compile os assets**
```bash
npm run dev
```

9. **Inicie o servidor**
```bash
php artisan serve
```

Acesse: `http://localhost:8000`

---

## 📁 Estrutura do Projeto

```
soserp/
├── app/
│   ├── Http/
│   │   └── Middleware/
│   │       └── IdentifyTenant.php
│   ├── Livewire/
│   │   ├── SuperAdmin/
│   │   │   ├── Dashboard.php
│   │   │   ├── Tenants.php
│   │   │   ├── Modules.php
│   │   │   └── Plans.php
│   │   └── Invoicing/
│   │       ├── Clients.php
│   │       ├── Suppliers.php
│   │       ├── Products.php
│   │       ├── Categories.php
│   │       ├── Brands.php
│   │       └── Invoices.php
│   ├── Models/
│   │   ├── Client.php
│   │   ├── Supplier.php
│   │   ├── Product.php
│   │   ├── Category.php
│   │   ├── Brand.php
│   │   ├── TaxRate.php
│   │   ├── InvoicingInvoice.php
│   │   └── InvoicingInvoiceItem.php
│   └── Traits/
│       └── ManagesFileUploads.php
├── database/
│   ├── migrations/
│   │   ├── 2025_10_02_183222_create_clients_table.php
│   │   ├── 2025_10_02_234000_create_invoicing_invoices_table.php
│   │   ├── 2025_10_03_000500_add_country_and_logo_to_clients_table.php
│   │   ├── 2025_10_03_001000_create_invoicing_suppliers_table.php
│   │   ├── 2025_10_03_001100_create_invoicing_categories_table.php
│   │   ├── 2025_10_03_001200_create_invoicing_brands_table.php
│   │   ├── 2025_10_03_001300_add_category_and_brand_to_products_table.php
│   │   ├── 2025_10_03_002000_update_images_to_upload_fields.php
│   │   ├── 2025_10_03_003000_create_invoicing_tax_rates_table.php
│   │   └── 2025_10_03_003100_update_products_tax_system.php
│   └── seeders/
│       └── TaxRateSeeder.php
├── resources/
│   └── views/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── components/
│       │   └── delete-confirmation-modal.blade.php
│       └── livewire/
│           ├── superadmin/
│           └── invoicing/
│               ├── clients/
│               ├── suppliers/
│               ├── products/
│               ├── categories/
│               ├── brands/
│               └── invoices/
├── routes/
│   └── web.php
└── storage/
    └── app/
        └── public/
            ├── clients/{id}/logo_*.ext
            ├── suppliers/{id}/logo_*.ext
            └── products/{id}/
                ├── featured_*.ext
                └── gallery/gallery_*.ext
```

---

## 🎯 Funcionalidades Detalhadas

### Sistema de Upload Organizado

```
storage/public/
├── clients/
│   ├── 1/logo_empresa-abc.jpg
│   └── 2/logo_joao-silva.jpg
├── suppliers/
│   └── 1/logo_fornecedor-alfa.jpg
└── products/
    └── 1/
        ├── featured_notebook-dell.jpg
        └── gallery/
            ├── gallery_1_timestamp.jpg
            └── gallery_2_timestamp.jpg
```

**Benefícios:**
- ✅ Organização por ID de entidade
- ✅ Nomes descritivos com slug
- ✅ Delete automático de pasta ao excluir entidade
- ✅ Fácil backup e migração

### Sistema de Taxas de IVA Angola

**Taxas Padrão:**
- **IVA 14%** - Taxa Geral
- **IVA 7%** - Taxa Reduzida (bens de primeira necessidade)
- **IVA 5%** - Taxa Especial

**Motivos de Isenção AGT:**
- M01 - Artigo 9.º, n.º 1
- M02 - Artigo 12.º
- M04 - Regime Especial de Isenção
- M10 - Bens de primeira necessidade
- M11 - Produtos farmacêuticos
- M12 - Transportes de passageiros
- M13 - Serviços de educação
- M14 - Serviços de saúde
- M15 - Operações financeiras
- M16 - Operações imobiliárias
- M99 - Outros motivos

### Menu Hierárquico

```
📄 Faturação ▼
├─ 👥 Clientes
├─ 🚚 Fornecedores
├─ 📦 Produtos
├─ 📁 Categorias
├─ 🏷️ Marcas
└─ 📃 Faturas
```

**Features:**
- ✅ Expand/Collapse com animação
- ✅ Abre automaticamente na rota ativa
- ✅ Alpine.js x-collapse
- ✅ Ícones coloridos únicos

### Modal de Confirmação de Delete

```
┌──────────────────────────────────┐
│  ⚠️  Confirmar Exclusão          │
│                                  │
│  Tem certeza que deseja excluir  │
│  o cliente:                      │
│                                  │
│  ┌────────────────────────────┐ │
│  │  João da Silva             │ │
│  └────────────────────────────┘ │
│                                  │
│  ⚠️ Esta ação não pode ser       │
│     desfeita!                    │
│                                  │
│  [🗑️ Sim, Excluir] [❌ Cancelar]│
└──────────────────────────────────┘
```

**Features:**
- ✅ Componente reutilizável
- ✅ Animações suaves
- ✅ Nome do item destacado
- ✅ Overlay clicável

---

## 🗺️ Roadmap

### ✅ Concluído

- [x] Sistema Multi-tenant
- [x] Autenticação e Autorização
- [x] Super Admin Dashboard
- [x] Módulo de Faturação completo
- [x] Clientes com país/província dinâmica
- [x] Fornecedores
- [x] Produtos com imagens
- [x] Categorias hierárquicas
- [x] Marcas
- [x] Sistema de Taxas IVA Angola
- [x] Upload organizado por entidade
- [x] Modal de confirmação delete
- [x] Menu hierárquico colapsável
- [x] Filtros avançados em todas áreas
- [x] Stats cards com métricas
- [x] Paginação customizável

### 🚧 Em Desenvolvimento

- [ ] Gestão de Taxas (CRUD para TaxRates)
- [ ] Relatórios e Dashboard analítico
- [ ] Exportação de Faturas (PDF/Excel)
- [ ] Integração AGT Angola (exportação XML)
- [ ] Gestão de Stocks avançada
- [ ] Histórico de movimentações
- [ ] Multi-idioma (PT, EN)

### 📅 Planejado

- [ ] Módulo de Compras
- [ ] Módulo de Vendas (POS)
- [ ] Módulo de Recursos Humanos
- [ ] Módulo de Contabilidade
- [ ] Módulo de CRM
- [ ] API REST completa
- [ ] Aplicação Mobile (Flutter)
- [ ] Notificações em tempo real
- [ ] Backup automático
- [ ] Importação de dados (Excel/CSV)

---

## 📊 Estatísticas do Projeto

| Métrica | Quantidade |
|---------|------------|
| **Models** | 12 |
| **Migrations** | 15 |
| **Livewire Components** | 11 |
| **Views Blade** | 25+ |
| **Rotas** | 11 |
| **Middlewares** | 2 |
| **Traits** | 1 |
| **Seeders** | 1 |
| **Linhas de Código** | ~12.000+ |

---

## 🤝 Contribuição

Contribuições são bem-vindas! Por favor, siga estes passos:

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

---

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

## 👨‍💻 Autor

Desenvolvido com ❤️ por **[Seu Nome]**

- 🌐 Website: [seusite.com](https://seusite.com)
- 📧 Email: seu@email.com
- 💼 LinkedIn: [seu-perfil](https://linkedin.com/in/seu-perfil)

---

## 🙏 Agradecimentos

- Laravel Team
- Livewire Team
- TailwindCSS Team
- Comunidade Open Source

---

<p align="center">
  Feito com ❤️ para Angola 🇦🇴
</p>
