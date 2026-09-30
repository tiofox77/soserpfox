# Auditoria de indexacao — 21/09/2026

## Imagem recebida

1 URL noindex, 1 redireccionamento, 1 alternativa canonica, 9 detectadas nao
indexadas e 3 rastreadas nao indexadas. A imagem nao identifica as URLs.
Nao e possivel atribuir as causas a paginas concretas sem as listas do Search Console.

## Verificacao de producao (somente leitura)

robots.txt e sitemap.xml: HTTP 200. As 12 URLs do sitemap responderam 200,
sem redireccionamento, sem X-Robots-Tag e com meta robots index/follow.
Paginas verificadas: /, /modulos, /modulos/vendas, /modulos/rh, /modulos/hotel,
/modulos/salao, /modulos/oficina, /modulos/restaurant, /privacidade, /termos,
/agendar/softec-salao, /hotel/booking/softec-hotel.
Login e registo nao constam do sitemap.

As paginas do produto possuem canonical proprio e um H1. As duas paginas
publicas React (salao/hotel) possuem metadados, mas nenhum H1 no HTML inicial.
Isto nao prova a causa de nao indexacao: o Google tambem pode renderizar JavaScript.

## Correcao local

O componente ecra-react aceita conteudo inicial por slot e mantem o esqueleto
original quando nao ha slot. O layout publico fornece H1 e descricao reais,
escapados, visiveis antes da montagem do React. Nao e conteudo oculto para bots.
A interface interactiva substitui esse bloco na montagem; nao mudam reservas,
pagamentos, permissoes ou dados privados.

17 testes passaram, 233 assercoes. Ha um aviso preexistente de depreciacao
de metadados PHPUnit em doc-comments.
Alteracoes locais preexistentes de SEO e faturacao foram preservadas.

## Pendente

Obter as URLs das categorias do Search Console, inspeccionar canonical escolhido,
ultima visita e resultado do teste publicado. Depois de aprovar/publicar as
alteracoes, solicitar indexacao das paginas publicas relevantes. Nao remover
noindex de login, aplicacao, paginas vazias ou variantes de mesa para zerar contagens.
Nao e possivel garantir indexacao nem prazo.

## Publicacao autorizada

Publicado em 21/09/2026 apos confirmacao do utilizador. Copia anterior em
`C:/Users/carlosfox/.codex/backups/seo-20260921`. Apenas os dois templates foram
enviados, conferidos por MD5; views compiladas e OPcache limpos.
Validacao HTTP em producao: salao e hotel respondem 200 com o bloco de conteudo
inicial e um H1 com o nome correcto. /modulos/rh e /sitemap.xml continuam HTTP 200.
Nao foi submetido pedido de indexacao no Search Console; faltam as listas de URLs.

Referencias:
- https://support.google.com/webmasters/answer/7440203?hl=en
- https://developers.google.com/search/docs/crawling-indexing/sitemaps/overview
