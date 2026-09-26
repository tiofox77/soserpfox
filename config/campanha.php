<?php

/*
|--------------------------------------------------------------------------
| CAMPANHA — percurso de inscrição (aterragem → registo)
|--------------------------------------------------------------------------
|
| Preserva o módulo por onde a pessoa entrou (para o registo pré-seleccionar o
| plano certo) e a atribuição da campanha (UTM/fbclid), sem pôr dados pessoais
| nos endereços — a atribuição viaja na sessão, não no URL.
|
| O mapa `modulos` ESPELHA `App\Http\Controllers\ModulePagesController::$modules`
| (cada módulo aponta o seu `plan_slug`). Um teste garante que não divergem.
*/

return [

    // slug da página do módulo  =>  slug do plano público a pré-seleccionar.
    'modulos' => [
        'vendas' => 'pacote-vendas',
        'rh' => 'pacote-rh',
        'hotel' => 'pacote-hotel',
        'salao' => 'pacote-salao',
        'oficina' => 'pacote-oficina',
        'restaurant' => 'pacote-restaurante',
    ],

    // O nome do módulo, como o formulário do registo o diz («Está a
    // registar-se para: Vendas»). Mesmas chaves do mapa de cima.
    'nomes_dos_modulos' => [
        'vendas' => 'Vendas e Faturação',
        'rh' => 'Recursos Humanos',
        'hotel' => 'Hotel',
        'salao' => 'Salão de Beleza',
        'oficina' => 'Oficina',
        'restaurant' => 'Restaurante',
    ],

    // Os parâmetros de atribuição que se guardam (nenhum é dado pessoal).
    'atribuicao' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'],

    /*
    | EXCLUIR TESTES dos resultados comerciais.
    |
    | Uma inscrição conta como TESTE (e sai das contas comerciais) quando o
    | email OU o NIF estão nesta lista, ou o nome/email contém um dos padrões.
    | Preenche-se à medida que se identificam — nunca por adivinhação.
    */
    'testes' => [
        'emails' => array_filter(array_map('trim', explode(',', (string) env('CAMPANHA_TESTES_EMAILS', '')))),
        'nifs' => array_filter(array_map('trim', explode(',', (string) env('CAMPANHA_TESTES_NIFS', '')))),
        // Substrings (minúsculas) que marcam um registo de teste no nome ou email.
        'padroes' => ['teste', 'test+', 'demo', 'exemplo.', 'example.', 'mailinator', '+test'],
    ],
];
