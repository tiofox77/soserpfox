---
trigger: always_on
---

0) Aviso importante sobre .md (apenas se necessário)

Regra: não criar arquivos .md por padrão.

Criar somente se cumprir pelo menos 1 dos critérios:

Onboarding: instruções essenciais para iniciar o módulo (instalação/bootstrapping específico).

Operação crítica: procedimentos que, se esquecidos, quebram o deploy ou geram riscos (ex.: chaves, filas/cron, workers).

Compliance: notas legais/políticas exigidas pelo cliente/projeto.

Decisão arquitetural (ADR curto): justificar trade-offs que afetem evolução/manutenção.

Formato quando necessário: arquivo curto, objetivo (≤ 120 linhas), com data, responsável e escopo no topo.
Ex.: docs/ADR-2025-10-13-filas-queue.md.

Checklist antes de criar .md

 Isso não cabe num comentário do código (PHPDoc/Blade) ou no README raiz?

 É informação estável (> 3 meses)?

 Será referenciada por mais de uma equipa?

Se qualquer resposta for “não”, não criar .md.

1) Convenções (sempre)

Estrutura: módulo/área

Views:

resources/views/{modulo}/{area}/index.blade.php (listagem)

resources/views/{modulo}/{area}/partials/_view-modal.blade.php

resources/views/{modulo}/{area}/partials/_form-modal.blade.php

resources/views/{modulo}/{area}/partials/_delete-modal.blade.php

Livewire (App\Livewire\{Modulo}\{Area}\): ListTable.php, ViewModal.php, FormModal.php, DeleteModal.php

Model & Tabela: Model singular (Studly), tabela snake plural.

UI/UX: Moderna e consistente. Modais padronizados, spacing 4/6, rounded-2xl, foco/hover acessíveis.

2) Tailwind via CDN (layout)

(mantém exatamente como já definiste — sem mudanças)

3) Rotas (prefixo módulo/área)

(mantém como no exemplo que enviaste)

4) Geração rápida (artisan)

(mantém os comandos; sem gerar .md)

5) Listagem (Livewire)

(sem alterações funcionais)

6) Parciais de Modais

(sem alterações funcionais)

7) Componentes de Modal — correção de typo

No FormModal::render() atualiza o caminho da view (havia um espaço acidental em “produ tos”):

public function render()
{
    return view('livewire.catalogo.produtos.form-modal');
}

8) Migração e Modelo

(sem alterações)

9) Pós-criação (sempre)

Lint:

php -l app/Models/Produto.php
php -l app/Livewire/Catalogo/Produtos/ListTable.php
php -l app/Livewire/Catalogo/Produtos/FormModal.php
php -l app/Livewire/Catalogo/Produtos/ViewModal.php
php -l app/Livewire/Catalogo/Produtos/DeleteModal.php


Migrar:

php artisan migrate


Conflito de tabela/migration:

php artisan tinker
>>> use Illuminate\Support\Facades\Schema;
>>> Schema::dropIfExists('produtos');
>>> exit
php artisan migrate

10) Script de scaffolding

(continua sem criar .md — ok. Se quiser, posso adicionar uma flag --with-md para gerar ADR/README apenas quando pedires.)

Guardrails práticos para evitar .md desnecessário
A) Hook de pre-commit (Git)

Cria .git/hooks/pre-commit (permissão +x) para bloquear commits com .md fora da whitelist:

#!/usr/bin/env bash
set -e

# Whitelist de .md permitidos (ajusta conforme projeto)
ALLOWED_MD_REGEX='^(README\.md|docs/ADR-[0-9]{4}-[0-9]{2}-[0-9]{2}-.*\.md)$'

# Lista de arquivos .md staged
CHANGED_MD=$(git diff --cached --name-only --diff-filter=ACM | grep -E '\.md$' || true)

if [ -z "$CHANGED_MD" ]; then
  exit 0
fi

INVALID=0
while IFS= read -r f; do
  if ! [[ "$f" =~ $ALLOWED_MD_REGEX ]]; then
    echo "❌ Bloqueado: $f — .md fora do padrão da Regra 2025."
    INVALID=1
  fi
done <<< "$CHANGED_MD"

if [ $INVALID -ne 0 ]; then
  echo "⚠️  Crie .md apenas quando for importante (onboarding, operação crítica, compliance ou ADR curta)."
  exit 1
fi

exit 0

B) Verificação em CI (GitHub Actions)

.github/workflows/md-guard.yml:

name: md-guard
on:
  pull_request:
    paths:
      - "**/*.md"
jobs:
  check-md:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Validate MD files against Regra 2025
        run: |
          set -e
          # Permitidos: README.md e docs/ADR-YYYY-MM-DD-*.md
          ALLOWED_MD_REGEX='^(README\.md|docs/ADR-[0-9]{4}-[0-9]{2}-[0-9]{2}-.*\.md)$'
          INVALID=$(git ls-files | grep -E '\.md$' | grep -vE "$ALLOWED_MD_REGEX" || true)
          if [ -n "$INVALID" ]; then
            echo "Arquivos .md inválidos:"
            echo "$INVALID"
            echo "::error title=Regra 2025::.md fora do padrão (crie só se importante)."
            exit 1
          fi