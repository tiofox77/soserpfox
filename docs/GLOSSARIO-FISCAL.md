# Glossário fiscal PT · EN · FR

*A referência de todos os lotes da tradução. Escrito antes do lote 1.*

Existe para uma razão só: **o mesmo conceito tem de ter sempre a mesma palavra**.
Sem isto, um ecrã diz "invoice", o outro diz "bill", e o cliente inglês fica
sem saber se são a mesma coisa. Quando houver dúvida num lote, é aqui que se
decide — e o que se decidir volta para cá.

## Documentos

| Português | English | Français | Nota |
|---|---|---|---|
| Factura | Invoice | Facture | Documento fiscal AGT — **o documento em si não se traduz** (ver abaixo) |
| Factura-recibo | Invoice-receipt | Facture-reçu | |
| Factura proforma / Proforma | Proforma invoice | Facture proforma | Não é documento fiscal |
| Nota de crédito | Credit note | Avoir | **Não** "credit note" em FR — o termo comercial é *avoir* |
| Nota de débito | Debit note | Note de débit | |
| Recibo | Receipt | Reçu | |
| Guia de remessa | Delivery note | Bon de livraison | |
| Guia de transporte | Transport note | Bon de transport | |
| Orçamento | Quote | Devis | |
| Extracto de conta | Account statement | Relevé de compte | |
| Nota de encomenda | Purchase order | Bon de commande | |

## Estados e ciclo de vida

| Português | English | Français |
|---|---|---|
| Rascunho | Draft | Brouillon |
| Emitida | Issued | Émise |
| Paga | Paid | Payée |
| Por pagar / Pendente | Unpaid / Pending | Impayée / En attente |
| Vencida | Overdue | Échue |
| Anulada | Cancelled | Annulée |
| Parcialmente paga | Partially paid | Partiellement payée |
| Comunicada à AGT | Reported to AGT | Transmise à l'AGT |

## Dinheiro e impostos

| Português | English | Français | Nota |
|---|---|---|---|
| Subtotal | Subtotal | Sous-total | |
| Desconto | Discount | Remise | |
| IVA | VAT | TVA | |
| Taxa de IVA | VAT rate | Taux de TVA | |
| Isento de IVA | VAT exempt | Exonéré de TVA | |
| Retenção na fonte | Withholding tax | Retenue à la source | |
| IRT | IRT | IRT | Imposto angolano — **sigla não se traduz** |
| Total | Total | Total | |
| Valor em dívida | Amount due | Solde dû | |
| Kz / Kwanza | Kz / Kwanza | Kz / Kwanza | **Nunca traduzir nem converter** |

## Entidades e catálogo

| Português | English | Français |
|---|---|---|
| Cliente | Customer | Client |
| Fornecedor | Supplier | Fournisseur |
| Artigo / Produto | Item / Product | Article / Produit |
| Serviço | Service | Service |
| Armazém | Warehouse | Entrepôt |
| Stock / Existências | Stock | Stock |
| Categoria | Category | Catégorie |
| Unidade | Unit | Unité |
| NIF | Tax ID (NIF) | NIF | Sigla angolana — manter |
| Série | Series | Série |

## Acções — a regra do botão

Um botão diz **o que acontece**, no imperativo, e a mensagem que se segue diz
**o que aconteceu**, no passado.

| Português | English | Français |
|---|---|---|
| Guardar | Save | Enregistrer |
| Gravar rascunho | Save draft | Enregistrer le brouillon |
| Emitir | Issue | Émettre |
| Anular | Cancel document | Annuler |
| Cancelar *(fechar sem gravar)* | Cancel | Annuler |
| Eliminar | Delete | Supprimer |
| Imprimir | Print | Imprimer |
| Descarregar PDF | Download PDF | Télécharger le PDF |
| Enviar por email | Send by email | Envoyer par e-mail |
| Adicionar linha | Add line | Ajouter une ligne |
| Ver detalhes | View details | Voir les détails |
| Pesquisar | Search | Rechercher |
| Filtrar | Filter | Filtrer |

⚠️ **"Cancelar" tem dois sentidos em português** e as três línguas separam-nos:
fechar uma janela sem gravar (*Cancel* / *Annuler*) não é o mesmo que anular um
documento fiscal (*Cancel document* / *Annuler la facture*). Escolher mal aqui
faz alguém anular uma factura a pensar que está a fechar um formulário.

## O que NUNCA se traduz

- **O conteúdo dos documentos fiscais AGT** — factura, factura-recibo, nota de
  crédito, os códigos FT/FR/NC/ND, as menções legais de isenção, o SAFT-AO.
  É matéria legal angolana e a língua oficial é o português. Traduzir uma
  menção de isenção é fabricar um documento que a AGT não reconhece. O que se
  traduz é a **interface** que os produz.
- **Siglas angolanas**: AGT, NIF, IRT, INSS, IVA quando aparece como código.
- **`Kz`** e o formato dos números (`1.234,56`) — em todas as línguas.
- **Dados**: nomes de artigos, de clientes, de empresas. São deles, não nossos.
- **Nomes de planos**: Starter, Business, Enterprise, FOX Friendly.

## Armadilhas conhecidas

| Cuidado | Porquê |
|---|---|
| FR: *facture* ≠ *reçu* | Uma factura não é um recibo; confundir é erro contabilístico |
| FR: nota de crédito é ***avoir*** | "Note de crédit" existe mas não é o termo do comércio |
| EN: *customer* e não *client* | *Client* em EN puxa para serviços profissionais |
| EN: *item* nas linhas do documento, *product* no catálogo | São coisas diferentes no mesmo ecrã |
| FR: espaço antes de `: ; ! ?` | Regra tipográfica francesa — "Erreur :", não "Erreur:" |
| Plurais | `trans_choice`, nunca "1 artigo(s)" |
