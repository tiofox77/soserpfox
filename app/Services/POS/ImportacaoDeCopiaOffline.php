<?php

namespace App\Services\POS;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Importa uma cópia de segurança exportada do PWA.
 *
 * PARA QUE SERVE: a fila de sincronização vive em IndexedDB, no dispositivo.
 * Se o telemóvel se perde, se o navegador limpa os dados do site, ou se
 * alguém carrega em "Reiniciar tudo" antes de sincronizar, as vendas
 * desaparecem — e essas vendas já aconteceram, com dinheiro trocado e talão
 * entregue. Não há forma de as recuperar depois.
 *
 * A exportação tira um ficheiro dessa fila; esta classe volta a metê-la no
 * sistema.
 *
 * COMO: cada operação é encaminhada para o MESMO serviço que a sincronização
 * normal usa. Não há um segundo caminho de criação de facturas — se houvesse,
 * mais dia menos dia os dois divergiam e o que entrasse por aqui saía com
 * regras fiscais diferentes.
 *
 * REPETIR É SEGURO: a idempotência por local_uuid já lá estava. Importar duas
 * vezes o mesmo ficheiro, ou importar um ficheiro cujas vendas entretanto
 * sincronizaram sozinhas, não duplica nada — conta-as como "já existiam".
 */
class ImportacaoDeCopiaOffline
{
    public const FORMATO = 'soserp.pwa.copia';
    public const VERSAO  = 2;

    /**
     * @return array{
     *     importadas:int, ja_existiam:int, falhadas:int,
     *     clientes:int, rascunhos:int, ignoradas:int, erros:array<string>, detalhes:array
     * }
     */
    public function importar(array $copia, int $tenantId, int $userId): array
    {
        $resumo = [
            'importadas'  => 0,
            'ja_existiam' => 0,
            'falhadas'    => 0,
            'clientes'    => 0,
            'rascunhos'   => 0,
            'comandas'    => 0,
            'ignoradas'   => 0,
            'erros'       => [],
            'detalhes'    => [],
        ];

        // Os CLIENTES primeiro, sempre.
        //
        // Uma venda offline pode apontar para um cliente que também está por
        // sincronizar. Se a venda entrasse antes, o cliente ainda não existia
        // e ela caía no consumidor final — perdendo a quem se vendeu.
        foreach ($this->operacoes($copia, 'create_client') as $carga) {
            $this->importarCliente($carga, $tenantId, $resumo);
        }

        foreach ($this->operacoes($copia, 'create_pos_sale') as $carga) {
            $this->importarVenda($carga, $tenantId, $userId, $resumo);
        }

        // Usa o mesmo fluxo da sincronização: FT/FR são emitidas no servidor;
        // proformas mantêm o estado definido pelo controlador. O UUID evita
        // uma segunda emissão quando a cópia é importada novamente.
        foreach ($this->operacoes($copia, 'create_draft') as $carga) {
            $this->importarRascunho($carga, $tenantId, $resumo);
        }

        foreach (collect($this->operacoes($copia, 'sync_restaurant_order'))->unique('local_uuid') as $carga) {
            try {
                if ((int) activeTenantId() !== $tenantId || !\App\Models\Tenant::find($tenantId)?->hasModule('restaurant')) {
                    throw new \RuntimeException('A empresa activa precisa do módulo Restaurante.');
                }
                if (empty($carga['venue_id']) || !isset($carga['items'])) {
                    throw new \RuntimeException('Esta cópia não contém os dados completos da comanda. Exporte novamente no aparelho.');
                }
                $request = \Illuminate\Http\Request::create('/api/v1/restaurant/offline/comanda', 'POST', $carga);
                $response = app(\App\Http\Controllers\Api\Restaurant\ComandaOfflineController::class)
                    ->store($request, app(\App\Services\Restaurant\ComandaOffline::class));
                $body = $response->getData(true);
                if (!$response->isSuccessful() || empty($body['success'])) {
                    throw new \RuntimeException($body['error'] ?? 'Não foi possível recuperar a comanda.');
                }
                $resumo['comandas']++;
            } catch (\Throwable $e) {
                $resumo['falhadas']++;
                $resumo['erros'][] = 'Comanda '.($carga['local_uuid'] ?? '?').': '.$e->getMessage();
            }
        }

        // Os turnos não se importam: um turno aberto há três dias não se
        // reabre, e fechá-lo agora punha a data errada no fecho de caixa.
        // As vendas entram na mesma — ficam sem turno, que é o mesmo que já
        // acontece quando o turno fecha antes de a venda sincronizar.
        $resumo['ignoradas'] = count($this->operacoes($copia, 'open_pos_shift'))
            + count($this->operacoes($copia, 'close_pos_shift'));

        return $resumo;
    }

    /** Valida o ficheiro ANTES de tocar em seja o que for. */
    public function validar(array $copia, int $tenantId): ?string
    {
        if (($copia['formato'] ?? null) !== self::FORMATO) {
            return 'Este ficheiro não é uma cópia de segurança do SOS ERP.';
        }

        if ((int) ($copia['versao'] ?? 0) > self::VERSAO) {
            return 'A cópia foi feita por uma versão mais recente do sistema. Actualize antes de importar.';
        }

        // A empresa TEM de bater certo, e o carimbo TEM de existir.
        //
        // Sem esta verificação, o ficheiro de uma empresa entrava noutra: as
        // vendas ficavam na contabilidade errada e não havia como as tirar de
        // lá — uma factura emitida não se apaga.
        //
        // Um ficheiro sem carimbo não se pode verificar, logo não se importa.
        // Deixá-lo passar "porque é antigo" seria abrir a porta que esta
        // condição existe para fechar.
        $daCopia = $copia['tenant_id'] ?? null;

        if ($daCopia === null || $daCopia === '') {
            return 'A cópia não diz de que empresa é e por isso não pode ser importada.';
        }

        if ((int) $daCopia !== $tenantId) {
            return 'Esta cópia é de outra empresa (ID ' . (int) $daCopia . '). Mude para a empresa certa antes de importar.';
        }

        if (empty($copia['dados']['sync_queue']) && empty($copia['dados']['pos_sales'])) {
            return 'A cópia não tem nada por sincronizar.';
        }

        return null;
    }

    /** O que está na cópia, sem importar nada. */
    public function inventario(array $copia): array
    {
        return [
            'vendas'    => count($this->operacoes($copia, 'create_pos_sale')),
            'clientes'  => count($this->operacoes($copia, 'create_client')),
            'rascunhos' => count($this->operacoes($copia, 'create_draft')),
            'comandas' => count(collect($this->operacoes($copia, 'sync_restaurant_order'))->unique('local_uuid')),
            'turnos'    => count($this->operacoes($copia, 'open_pos_shift'))
                + count($this->operacoes($copia, 'close_pos_shift')),
            'gerado_em'  => $copia['gerado_em'] ?? null,
            'dispositivo' => $copia['dispositivo'] ?? null,
            'operador'   => $copia['utilizador']['nome'] ?? null,
        ];
    }

    /** As cargas das operações de um dado tipo que ainda não foram enviadas. */
    private function operacoes(array $copia, string $op): array
    {
        $fila = $copia['dados']['sync_queue'] ?? [];

        // O filtro é explicitamente `!== null`: um array_filter sem callback
        // descarta o que for falso, e uma carga vazia — `[]` — é falsa. A
        // operação desaparecia da contagem como se não existisse.
        return array_values(array_filter(
            array_map(
                fn ($j) => ($j['op'] ?? null) === $op ? ($j['payload'] ?? []) : null,
                is_array($fila) ? $fila : []
            ),
            fn ($carga) => $carga !== null
        ));
    }

    private function importarCliente(?array $carga, int $tenantId, array &$resumo): void
    {
        if (!$carga || empty($carga['name'])) {
            return;
        }

        try {
            $localUuid = $carga['local_uuid'] ?? null;

            // A mesma ordem de desduplicação do ClientController: primeiro o
            // identificador local, depois o NIF.
            if ($localUuid && Schema::hasColumn('invoicing_clients', 'local_uuid')) {
                $ja = Client::where('tenant_id', $tenantId)->where('local_uuid', $localUuid)->first();

                if ($ja) {
                    $resumo['ja_existiam']++;
                    return;
                }
            }

            if (!empty($carga['nif'])) {
                $ja = Client::where('tenant_id', $tenantId)->where('nif', $carga['nif'])->first();

                if ($ja) {
                    $resumo['ja_existiam']++;
                    return;
                }
            }

            $cliente = new Client();
            $cliente->tenant_id = $tenantId;

            if ($localUuid && Schema::hasColumn('invoicing_clients', 'local_uuid')) {
                $cliente->local_uuid = $localUuid;
            }

            $cliente->name    = $carga['name'];
            $cliente->nif     = $carga['nif'] ?? null;
            $cliente->type    = ($carga['type'] ?? null) === 'pessoa_juridica' ? 'pessoa_juridica' : 'pessoa_fisica';
            $cliente->email   = $carga['email'] ?? null;
            $cliente->phone   = $carga['phone'] ?? null;
            $cliente->mobile  = $carga['mobile'] ?? null;
            $cliente->address = $carga['address'] ?? null;
            $cliente->city    = $carga['city'] ?? null;
            $cliente->country = $carga['country'] ?? 'Angola';
            $cliente->is_active = true;
            $cliente->save();

            $resumo['clientes']++;
        } catch (\Throwable $e) {
            $resumo['falhadas']++;
            $resumo['erros'][] = 'Cliente "' . ($carga['name'] ?? '?') . '": ' . $e->getMessage();

            Log::error('ImportacaoDeCopiaOffline: cliente falhou', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Rascunhos: factura (FT), proforma, nota de crédito (NC).
     *
     * Chama o MESMO handler que a sincronização normal usa — o
     * DraftController — com o pedido montado à mão. Não há aqui uma segunda
     * forma de criar documentos: se houvesse, mais dia menos dia divergiam, e
     * o que entrasse por aqui saía com regras diferentes das do resto.
     *
     * Sobre a AGT: estes documentos entram como RASCUNHO, sem número e sem
     * hash. Não tocam na cadeia. O número AGT e o encadeamento só acontecem na
     * finalização, feita por uma pessoa no módulo de facturação — igual ao que
     * acontece quando o dispositivo sincroniza sozinho.
     */
    private function importarRascunho(?array $carga, int $tenantId, array &$resumo): void
    {
        if (!$carga || empty($carga['doc_type']) || empty($carga['items'])) {
            return;
        }

        $localUuid = $carga['local_uuid'] ?? null;

        try {
            // O DraftController não desduplica por local_uuid — a
            // sincronização normal também duplica um rascunho reenviado depois
            // de uma resposta perdida. Aqui a repetição é garantida (importar o
            // mesmo ficheiro duas vezes é o caso normal), portanto verifica-se.
            if ($localUuid && $carga['doc_type'] !== 'proforma') {
                $ja = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $tenantId)
                    ->where('local_uuid', $localUuid)
                    ->exists();

                if ($ja) {
                    $resumo['ja_existiam']++;

                    return;
                }
            }

            $pedido = \Illuminate\Http\Request::create('/api/v1/invoicing/drafts', 'POST', $carga);
            $pedido->headers->set('Accept', 'application/json');

            $resposta = app(\App\Http\Controllers\Api\Invoicing\DraftController::class)->store($pedido);

            if ($resposta->getStatusCode() >= 300) {
                $corpo = json_decode($resposta->getContent(), true);

                throw new \RuntimeException(
                    $corpo['error'] ?? ('o servidor recusou (HTTP ' . $resposta->getStatusCode() . ')')
                );
            }

            $corpo = json_decode($resposta->getContent(), true);
            if (!empty($corpo['duplicated'])) {
                $resumo['ja_existiam']++;
            } else {
                $resumo['rascunhos']++;
            }
        } catch (\Throwable $e) {
            $resumo['falhadas']++;
            $resumo['erros'][] = strtoupper($carga['doc_type'] ?? '?') . ' '
                . substr((string) $localUuid, 0, 12) . '…: ' . $e->getMessage();

            Log::error('ImportacaoDeCopiaOffline: rascunho falhou', [
                'doc_type'   => $carga['doc_type'] ?? null,
                'local_uuid' => $localUuid,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function importarVenda(?array $carga, int $tenantId, int $userId, array &$resumo): void
    {
        if (!$carga || empty($carga['local_uuid']) || empty($carga['items'])) {
            return;
        }

        $localUuid = $carga['local_uuid'];

        try {
            $jaExistia = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $tenantId)
                ->where('local_uuid', $localUuid)
                ->exists();

            // O MESMO serviço da sincronização normal. Ver o cabeçalho da
            // classe: um segundo caminho de criação de facturas divergia.
            $factura = app(PosSaleService::class)->createFromPayload($carga, $tenantId, $userId);

            if ($jaExistia) {
                $resumo['ja_existiam']++;
            } else {
                $resumo['importadas']++;
                $resumo['detalhes'][] = [
                    'local_uuid' => $localUuid,
                    'numero'     => $factura->invoice_number,
                    'total'      => (float) $factura->total,
                ];
            }
        } catch (\Throwable $e) {
            $resumo['falhadas']++;
            $resumo['erros'][] = 'Venda ' . substr($localUuid, 0, 12) . '…: ' . $e->getMessage();

            Log::error('ImportacaoDeCopiaOffline: venda falhou', [
                'local_uuid' => $localUuid,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
