# SEO SOSERP comparado com KiandaStay — 16/09/2026

Referência consultada: projeto KiandaStay em `C:/laragon2/www/okavangobook`, especialmente `resources/views/partials/seo.blade.php`, `public/robots.txt` e `app/Http/Controllers/SitemapController.php`.

## Resultado da comparação

Ambos já têm HTML renderizado no servidor, títulos e descrições por página, canonical, Open Graph, Twitter Cards e sitemap dinâmico. O SOSERP já tem também Organization, SoftwareApplication, BreadcrumbList e FAQ baseado no conteúdo visível. Não há evidência nesta auditoria de que copiar mais tags determine a posição no Google.

O sitemap de produção respondeu HTTP 200 e já exclui login e registo. A página pública de RH respondeu HTTP 200, com canonical correto e indexação permitida. A imagem do Search Console descreve o estado conhecido pelo Google, que pode ser anterior ao estado atual do site. Não foi consultada uma sessão autenticada do Search Console.

## Alterações desta revisão

- Restaurante incluído na navegação e rodapé partilhados dos módulos.
- Título, descrição e texto alternativo das imagens nas Twitter Cards dos módulos; texto alternativo Open Graph e ligação ao sitemap.
- Canonical da página inicial obtido da mesma fonte que o sitemap e os dados estruturados, incluindo fallback quando a configuração está vazia.
- Hierarquia H1 → H2 nos cartões da lista dos módulos.
- Público do restaurante incluído no comparativo dos módulos.

## Validação

`php artisan test tests/Feature/SeoDoSitePublicoTest.php tests/Feature/DadosEstruturadosTest.php`

Resultado: 15 testes aprovados, 226 assertions. O PHPUnit emitiu um aviso de depreciação sobre metadados de teste existentes em doc-comments.

Alterações desta revisão não publicadas. Foram preservadas as alterações existentes no controlador e na página individual dos módulos.

## Depois da publicação

1. Submeter ou confirmar `https://soserp.vip/sitemap.xml` no Search Console.
2. Inspecionar a página inicial e cada página de módulo; executar o teste do URL publicado e solicitar indexação.
3. Comparar a data de rastreamento, o canonical escolhido e eventuais respostas 403/429/5xx nos logs de acesso/CDN.
4. Acompanhar impressões e consultas por página. Login e registo devem continuar fora do sitemap.

Um sitemap facilita a descoberta, mas não garante rastreamento, indexação ou posicionamento. Referência: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
