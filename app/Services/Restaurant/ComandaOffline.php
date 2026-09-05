<?php

namespace App\Services\Restaurant;

use App\Models\Restaurant\DiningTable;
use App\Models\Restaurant\Order;
use App\Models\Restaurant\OrderEvent;
use App\Models\Restaurant\OrderItem;
use App\Models\Restaurant\RestaurantSettings;
use App\Models\Treasury\PaymentMethod;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A comanda feita sem rede, reposta no servidor.
 *
 * O POS online faz isto em quatro viagens — abrir, juntar artigos, mandar à
 * cozinha, receber — e cada uma precisa do id que a anterior devolveu. Sem
 * rede não há id nenhum, por isso o dispositivo guarda a comanda INTEIRA e
 * manda-a de uma vez quando puder. Este serviço volta a percorrer os mesmos
 * passos pela ordem certa, com o cuidado de poder ser chamado outra vez sem
 * duplicar nada: a rede que oscila reenvia, e reenviar é o caso normal, não a
 * excepção.
 *
 * O que aqui se decidiu, e porquê:
 *
 *  - UMA COMANDA NUNCA SE PERDE. Se a mesa entretanto foi ocupada por outra
 *    comanda, esta abre ao balcão com uma nota a dizer que mesa era. Recusar
 *    seria deitar fora comida já servida e dinheiro já cobrado; juntar às
 *    cegas seria pôr a conta de uns na mesa de outros.
 *
 *  - O SERVIDOR MANDA NO PREÇO, o dispositivo manda no MÉTODO. O preço do
 *    artigo é o que está no catálogo agora, como no POS online. Se entretanto
 *    mudou, o valor recebido reparte-se pelo total do documento e fica um
 *    aviso; deixar rebentar por causa de dois cêntimos apagaria uma venda já
 *    paga.
 *
 *  - O QUE O SERVIDOR RECUSA, RECUSA-SE INTEIRO E EM VOZ ALTA. Um prato sem
 *    ficha técnica (quando a empresa a exige) faz a comanda toda parar, com o
 *    nome do prato na mensagem. Facturar sem ele seria receita por cobrar e
 *    stock por descontar, em silêncio.
 */
class ComandaOffline
{
    public function __construct(
        private RestaurantOrderService $comandas,
        private RestaurantCheckoutService $caixa,
        private RestaurantStockService $stock,
    ) {
    }

    /**
     * @return array{order: Order, avisos: array<int,string>, invoice: mixed}
     */
    public function repor(array $dados, int $tenantId, ?int $userId): array
    {
        // UMA COMANDA DE CADA VEZ — POR local_uuid.
        //
        // Descoberto pelo ensaio de carga: o retry de um aparelho pode chegar
        // DUAS VEZES AO MESMO TEMPO (o pedido original ainda em curso quando o
        // aparelho o repete). As protecções daqui são todas «vê se já existe,
        // senão cria» — perfeitas em série, corrida em paralelo: os dois
        // passavam no «não existe», um criava, e o outro rebentava com 1062
        // no envio à cozinha. O índice único salvou os dados; o 500 não — um
        // aparelho que recebe 500 repete para sempre.
        //
        // O GET_LOCK serializa só os pedidos DO MESMO local_uuid: o gémeo
        // espera uns milissegundos, e depois encontra tudo `jaExiste` e
        // devolve a mesma comanda. Comandas diferentes não se atrasam nada.
        $tranca = 'comanda-'.$tenantId.'-'.(string) $dados['local_uuid'];

        DB::select('SELECT GET_LOCK(?, 15)', [$tranca]);

        try {
            return $this->reporTrancado($dados, $tenantId, $userId);
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$tranca]);
        }
    }

    private function reporTrancado(array $dados, int $tenantId, ?int $userId): array
    {
        $avisos = [];
        $comanda = $this->abrir($dados, $tenantId, $userId, $avisos);

        $this->juntarArtigos($comanda, $dados['items'] ?? [], $tenantId, $userId, $avisos);

        $comanda = $comanda->fresh(['items']);

        if (!empty($dados['confirmar'])) {
            $this->mandarParaCozinha($comanda, $tenantId, $userId, $avisos);
            $comanda = $comanda->fresh(['items']);
        }

        $factura = null;

        if (!empty($dados['checkout'])) {
            $factura = $this->receber($comanda, $dados['checkout'], $tenantId, $userId, $avisos);
            $comanda = $comanda->fresh(['items']);
        }

        return ['order' => $comanda, 'avisos' => $avisos, 'invoice' => $factura];
    }

    // ── Abrir ------------------------------------------------------------

    private function abrir(array $dados, int $tenantId, ?int $userId, array &$avisos): Order
    {
        $uuid = (string) $dados['local_uuid'];

        $jaExiste = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('local_uuid', $uuid)
            ->first();

        if ($jaExiste) {
            return $jaExiste;
        }

        $mesaId = $dados['table_id'] ?? null;
        $canal = $dados['channel'] ?? ($mesaId ? 'table' : 'counter');
        $notas = $dados['notes'] ?? null;

        if ($mesaId && !$this->mesaEstaLivre((int) $mesaId, $tenantId)) {
            $nome = DiningTable::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereKey($mesaId)
                ->value('name') ?: ('#' . $mesaId);

            $avisos[] = "A mesa {$nome} já estava ocupada quando esta comanda subiu — abriu ao balcão.";
            $notas = trim(($notas ?? '') . " [Feita sem rede na mesa {$nome}, ocupada à chegada]");
            $mesaId = null;
            $canal = 'counter';
        }

        try {
            return $this->comandas->open([
                'local_uuid'  => $uuid,
                'venue_id'    => $dados['venue_id'] ?? null,
                'table_id'    => $mesaId,
                'channel'     => $canal,
                'guest_count' => $dados['guest_count'] ?? 1,
                'client_id'   => $dados['client_id'] ?? null,
                'notes'       => $notas,
            ], $tenantId, $userId);
        } catch (InvalidArgumentException $e) {
            // A mesa pode ter sido ocupada entre a verificação e a abertura —
            // duas pessoas a sincronizar ao mesmo tempo é precisamente o que
            // acontece quando a rede volta a uma sala inteira. Só se repete por
            // causa da mesa; o resto (turno fechado, estabelecimento inválido)
            // tem de continuar a subir à superfície.
            if (!$mesaId || !str_contains($e->getMessage(), 'mesa')) {
                throw $e;
            }

            $avisos[] = 'A mesa foi ocupada durante a sincronização — a comanda abriu ao balcão.';

            return $this->comandas->open([
                'local_uuid'  => $uuid,
                'venue_id'    => $dados['venue_id'] ?? null,
                'table_id'    => null,
                'channel'     => 'counter',
                'guest_count' => $dados['guest_count'] ?? 1,
                'client_id'   => $dados['client_id'] ?? null,
                'notes'       => trim(($notas ?? '') . ' [Mesa ocupada durante a sincronização]'),
            ], $tenantId, $userId);
        }
    }

    private function mesaEstaLivre(int $mesaId, int $tenantId): bool
    {
        $mesa = DiningTable::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($mesaId)
            ->first();

        if (!$mesa || !$mesa->is_active || in_array($mesa->status, ['blocked', 'cleaning'], true)) {
            return false;
        }

        return !Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('table_id', $mesaId)
            ->whereIn('status', Order::OPEN_STATUSES)
            ->exists();
    }

    // ── Artigos ----------------------------------------------------------

    private function juntarArtigos(Order $comanda, array $artigos, int $tenantId, ?int $userId, array &$avisos): void
    {
        foreach ($artigos as $linha) {
            $uuid = (string) ($linha['local_uuid'] ?? '');

            if ($uuid === '') {
                throw new InvalidArgumentException('Cada artigo da comanda offline tem de trazer o seu identificador.');
            }

            $jaLa = OrderItem::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('local_uuid', $uuid)
                ->exists();

            if ($jaLa) {
                continue;
            }

            $item = $this->comandas->addItem(
                $comanda,
                (int) $linha['product_id'],
                (float) $linha['quantity'],
                $linha['notes'] ?? null,
                $tenantId,
                $userId
            );

            $item->forceFill(['local_uuid' => $uuid])->saveQuietly();

            $this->avisarSePrecoMudou($linha, $item, $avisos);
        }
    }

    /**
     * O preço que o cliente ouviu ao balcão contra o que o catálogo diz agora.
     *
     * Não se corrige nada: o catálogo é a autoridade, como no POS online. Mas
     * uma diferença silenciosa entre o talão que o cliente levou e a factura
     * que fica nos livros é o género de coisa que só se descobre no fecho do
     * mês, e aí já ninguém se lembra da mesa.
     */
    private function avisarSePrecoMudou(array $linha, OrderItem $item, array &$avisos): void
    {
        $precoNoAparelho = isset($linha['unit_price']) ? (float) $linha['unit_price'] : null;

        if ($precoNoAparelho === null || abs($precoNoAparelho - (float) $item->unit_price) < 0.01) {
            return;
        }

        $avisos[] = sprintf(
            '%s: o aparelho cobrou %s e o catálogo tem %s — valeu o do catálogo.',
            $item->product_name,
            number_format($precoNoAparelho, 2, ',', '.'),
            number_format((float) $item->unit_price, 2, ',', '.')
        );
    }

    // ── Cozinha ----------------------------------------------------------

    private function mandarParaCozinha(Order $comanda, int $tenantId, ?int $userId, array &$avisos): void
    {
        if (!$comanda->items()->where('kitchen_status', 'draft')->exists()) {
            return;
        }

        try {
            $this->comandas->confirm($comanda, $tenantId, $userId);
        } catch (InvalidArgumentException $e) {
            $avisos[] = 'Não foi possível enviar à cozinha: ' . $e->getMessage();
        }
    }

    // ── Receber ----------------------------------------------------------

    private function receber(Order $comanda, array $pedido, int $tenantId, ?int $userId, array &$avisos)
    {
        $comanda = $this->darComoServida($comanda, $tenantId, $userId);

        $tipo = strtoupper($pedido['document_type'] ?? 'FR');

        $dados = [
            'document_type'   => $tipo,
            'client_id'       => $pedido['client_id'] ?? null,
            'idempotency_key' => $this->chaveDeIdempotencia($comanda->local_uuid),
            'item_ids'        => null,
        ];

        if ($tipo === 'FR') {
            $repartido = $this->repartirPeloTotal(
                $pedido['payments'] ?? [],
                ((int) ($pedido['payment_method_id'] ?? 0)) ?: null,
                $this->porFacturar($comanda, $tenantId),
                $tenantId,
                $avisos
            );

            $dados['payments'] = $repartido;
            $dados['payment_method_id'] = $repartido[0]['payment_method_id'] ?? null;
        }

        return $this->caixa->checkout($comanda, $dados, $tenantId, $userId);
    }

    /**
     * A chave de idempotencia do recebimento, derivada da comanda.
     *
     * Tem de ser a MESMA em cada reenvio — é o que impede a segunda tentativa
     * de emitir uma segunda factura da mesma conta. Derivada e não sorteada,
     * precisamente por isso. Sai em forma de UUID porque é isso que a coluna
     * e a validação do fecho esperam.
     */
    private function chaveDeIdempotencia(string $localUuid): string
    {
        $bytes = md5('comanda-offline:' . $localUuid, true);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return implode('-', [
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        ]);
    }

    /**
     * A conta foi servida e paga sem rede — o estado tem de o dizer.
     *
     * O fecho só aceita comandas prontas, servidas ou já parcialmente
     * facturadas. Sem rede não há cozinha nenhuma a passar o pedido a
     * "pronto": o ecrã de cozinha é outro aparelho, e sem rede não se falam.
     * Marcar como servida não é contornar o circuito — é registar o que
     * aconteceu de facto, e fica escrito no histórico da comanda.
     */
    private function darComoServida(Order $comanda, int $tenantId, ?int $userId): Order
    {
        if (in_array($comanda->status, ['ready', 'served', 'partially_billed'], true)) {
            return $comanda;
        }

        OrderItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $comanda->id)
            ->whereIn('kitchen_status', ['draft', 'queued', 'accepted', 'preparing', 'ready'])
            ->update(['kitchen_status' => 'served']);

        $comanda->update(['status' => 'served']);

        OrderEvent::withoutGlobalScopes()->create([
            'tenant_id'  => $tenantId,
            'order_id'   => $comanda->id,
            'user_id'    => $userId,
            'event'      => 'order_served_offline',
            'payload'    => ['motivo' => 'Servida e cobrada sem rede; reposta na sincronização.'],
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);

        $settings = RestaurantSettings::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();

        if ($settings?->consume_stock_on_kitchen) {
            $this->stock->consume($comanda->fresh('venue'), $tenantId, $userId);
        }

        return $comanda->fresh(['items']);
    }

    private function porFacturar(Order $comanda, int $tenantId): float
    {
        return (float) OrderItem::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('order_id', $comanda->id)
            ->where('kitchen_status', '!=', 'voided')
            ->whereColumn('billed_quantity', '<', 'quantity')
            ->sum('line_total');
    }

    /**
     * O dinheiro recebido, repartido pelo total que o documento vai ter.
     *
     * O dispositivo diz por que MÉTODOS o cliente pagou e em que proporção; o
     * quanto é o do documento. Se o preço mudou entretanto, o fecho recusaria
     * a soma por um cêntimo e a venda — já paga, já comida — ficava presa
     * para sempre numa fila. Reparte-se e avisa-se.
     */
    private function repartirPeloTotal(array $pagamentos, ?int $metodoUnico, float $total, int $tenantId, array &$avisos): array
    {
        $total = round($total, 2);

        $linhas = collect($pagamentos)
            ->map(fn ($p) => [
                'payment_method_id' => (int) ($p['payment_method_id'] ?? 0),
                'amount'            => (float) ($p['amount'] ?? 0),
            ])
            ->filter(fn ($p) => $p['payment_method_id'] > 0 && $p['amount'] > 0)
            ->values();

        if ($linhas->isEmpty()) {
            $metodo = $metodoUnico ?: $this->metodoPorOmissao($tenantId);

            if (!$metodo) {
                throw new InvalidArgumentException('A empresa não tem métodos de pagamento activos.');
            }

            return [['payment_method_id' => $metodo, 'amount' => $total]];
        }

        $recebido = round($linhas->sum('amount'), 2);

        if (abs($recebido - $total) < 0.01) {
            return $linhas->all();
        }

        $avisos[] = sprintf(
            'O aparelho recebeu %s e o documento ficou em %s — o valor foi repartido pelos mesmos métodos.',
            number_format($recebido, 2, ',', '.'),
            number_format($total, 2, ',', '.')
        );

        $escalado = $linhas->map(fn ($p) => [
            'payment_method_id' => $p['payment_method_id'],
            'amount'            => $recebido > 0 ? round($p['amount'] / $recebido * $total, 2) : 0.0,
        ])->all();

        // O arredondamento de cada linha não fecha necessariamente o total; a
        // última leva a diferença, senão o fecho recusa a soma.
        $diferenca = round($total - array_sum(array_column($escalado, 'amount')), 2);
        $ultima = count($escalado) - 1;
        $escalado[$ultima]['amount'] = round($escalado[$ultima]['amount'] + $diferenca, 2);

        return array_values(array_filter($escalado, fn ($p) => $p['amount'] > 0));
    }

    private function metodoPorOmissao(int $tenantId): ?int
    {
        return PaymentMethod::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->value('id');
    }
}
