<?php

namespace App\Console\Commands;

use App\Models\Invoicing\CreditNote;
use App\Models\AGT\AGTSubmission;
use App\Services\AGT\SignatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Acerta uma nota de crédito pela factura que ela corrige.
 *
 * PORQUE EXISTE. A NC4226S46906N/000002 da LUK-SIMOES saiu com 69 unidades
 * numa linha em que a factura só tinha 34 — o carrinho somava a quantidade
 * escrita à que já lá estava em vez de a substituir. A AGT recusou-a com E43:
 * «a soma dos valores a anular excede o montante ainda não anulado do
 * documento base». O defeito já está corrigido no ecrã; isto endireita as
 * notas que nasceram antes.
 *
 * O QUE FAZ, linha a linha: corta a quantidade ao que a factura ainda tem por
 * anular — contando o que outras notas emitidas já lhe tiraram — e refaz os
 * totais da linha e do documento.
 *
 * O HASH. Um documento emitido é imutável, e o `signComplete()` recusa-se, e
 * bem, a reassiná-lo. Aqui abre-se essa excepção de propósito e só ela: uma
 * nota RECUSADA pela AGT não existe do lado dela, e corrigi-la antes de ser
 * aceite é o caminho certo. Uma nota já ACEITE não se toca — anula-se e emite-se
 * outra. É por isso que este comando se recusa a mexer numa nota aceite.
 */
class AcertarNotaDeCredito extends Command
{
    protected $signature = 'nota-credito:acertar
                            {--tenant= : id da empresa}
                            {--id= : id da nota}
                            {--aplicar : escreve de facto (sem isto só mostra)}';

    protected $description = 'Corta as quantidades de uma nota de crédito ao que a factura ainda tem por anular';

    public function handle(): int
    {
        $nota = CreditNote::withoutGlobalScopes()
            ->with(['items', 'invoice.items'])
            ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', (int) $this->option('tenant')))
            ->find($this->option('id'));

        if (! $nota) {
            $this->error('Nota não encontrada. Use --id= (e --tenant= para confirmar a empresa).');

            return self::FAILURE;
        }

        $factura = $nota->invoice;

        if (! $factura) {
            $this->error('Esta nota não referencia factura nenhuma — não há por onde a acertar.');

            return self::FAILURE;
        }

        $this->line('<options=bold>' . $nota->credit_note_number . '</> sobre <options=bold>' . $factura->invoice_number . '</>');
        $this->line('  total da nota: ' . $this->kz($nota->total) . '   ·   total da factura: ' . $this->kz($factura->total));

        if ($this->foiAceite($nota)) {
            $this->error('A AGT ACEITOU esta nota. Não se altera um documento aceite: anule-o e emita outro.');

            return self::FAILURE;
        }

        // Quanto tem a factura por anular, linha a linha.
        $disponivel = $this->disponivelPorLinha($factura, $nota);

        $mudancas = [];
        $apurado  = [];   // id da linha => campos novos
        $novoSubtotal = 0.0;
        $novoImposto  = 0.0;

        foreach ($nota->items as $linha) {
            $k = $this->chave($linha->product_id, $linha->description);

            $tecto = $disponivel[$k] ?? 0;
            $atual = (float) $linha->quantity;
            $nova  = min($atual, max(0, $tecto));

            $unitario = (float) $linha->unit_price;
            $desconto = (float) ($linha->discount_percent ?? 0);

            $bruto     = $nova * $unitario;
            $descValor = round($bruto * $desconto / 100, 2);
            $liquido   = round($bruto - $descValor, 2);
            $imposto   = round($liquido * (float) ($linha->tax_rate ?? 0) / 100, 2);

            $novoSubtotal += $liquido;
            $novoImposto  += $imposto;

            if (abs($nova - $atual) > 0.0001) {
                $mudancas[] = [
                    'linha'    => $linha,
                    'de'       => $atual,
                    'para'     => $nova,
                    'subtotal' => $liquido,
                    'desconto' => $descValor,
                    'imposto'  => $imposto,
                    'total'    => round($liquido + $imposto, 2),
                ];
            }

            // Guarda-se sempre o apuramento, mesmo nas linhas que não mudam:
            // é com ele que se refazem os totais do documento. Num array à
            // parte e não em cima do modelo: uma propriedade inventada numa
            // Eloquent vira ATRIBUTO, e o `update()` ia gravar uma coluna
            // que não existe.
            $apurado[$linha->id] = [
                'quantity'        => $nova,
                'subtotal'        => $liquido,
                'discount_amount' => $descValor,
                'tax_amount'      => $imposto,
                'total'           => round($liquido + $imposto, 2),
                'debit_amount'    => $liquido,
            ];
        }

        $novoTotal = round($novoSubtotal + $novoImposto, 2);

        // Os totais do documento também têm de bater com as linhas: é o
        // `net_total` que viaja para a AGT, e um documento com as linhas
        // certas e o cabeçalho errado leva E23.
        $totaisFora = abs((float) ($nota->net_total ?? $nota->subtotal) - round($novoSubtotal, 2)) > 0.01
            || abs((float) ($nota->gross_total ?? $nota->total) - $novoTotal) > 0.01
            || abs((float) $nota->total - $novoTotal) > 0.01;

        if ($mudancas === [] && ! $totaisFora) {
            $this->info('Nada a acertar: as linhas cabem na factura e os totais batem certo.');

            return self::SUCCESS;
        }

        if ($mudancas === []) {
            $this->warn('As linhas estão certas; o que está fora são os totais do documento.');
        }

        $this->newLine();
        if ($mudancas !== []) {
        $this->table(
            ['artigo', 'qtd agora', 'qtd certa', 'novo total da linha'],
            collect($mudancas)->map(fn ($m) => [
                \Illuminate\Support\Str::limit((string) $m['linha']->description, 38),
                rtrim(rtrim(number_format($m['de'], 3, ',', '.'), '0'), ','),
                rtrim(rtrim(number_format($m['para'], 3, ',', '.'), '0'), ','),
                $this->kz($m['total']),
            ])->all()
        );
        }

        $this->line('  net_total gravado: ' . $this->kz($nota->net_total ?? $nota->subtotal)
            . '  →  ' . $this->kz(round($novoSubtotal, 2)));
        $this->line('  total da nota: ' . $this->kz($nota->total) . '  →  ' . $this->kz($novoTotal));
        $this->line('  (a factura tem ' . $this->kz($factura->total) . ')');

        if (! $this->option('aplicar')) {
            $this->newLine();
            $this->comment('A SECO. Corra com --aplicar para gravar, reassinar e pôr a nota outra vez na fila da AGT.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($nota, $apurado, $novoSubtotal, $novoImposto, $novoTotal) {
            foreach ($nota->items as $linha) {
                $linha->update($apurado[$linha->id]);
            }

            // OS TRÊS PARES DE TOTAIS. O que viaja para a AGT é o `net_total`
            // (e o `gross_total`), não o `subtotal` — o DocumentMapper prefere
            // aquele. Actualizar só o subtotal deixava o documento a declarar
            // 4.107.810,28 com linhas que somam 2.550.698,08, e a AGT devolvia
            // E23: «o netTotal não corresponde à soma das linhas».
            // PRIMEIRO tirar a assinatura. O `HasAGTSignature` recusa mexer
            // nos totais de um documento assinado, e é ele que garante que
            // ninguém altera um documento fiscal por acidente. Aqui a
            // excepção é deliberada: a nota foi RECUSADA pela AGT e não
            // existe do lado dela. Uma nota aceite já foi travada lá atrás.
            $nota->update([
                'jws_signature' => null,
                'hash'          => null,
                'saft_hash'     => null,
            ]);

            $nota->refresh();

            $nota->update([
                'subtotal'    => round($novoSubtotal, 2),
                'net_total'   => round($novoSubtotal, 2),
                'tax_amount'  => round($novoImposto, 2),
                'tax_payable' => round($novoImposto, 2),
                'gross_total' => $novoTotal,
                'total'       => $novoTotal,
            ]);

            $r = app(SignatureService::class, ['tenantId' => $nota->tenant_id])->signComplete($nota->fresh());

            $this->line('  assinatura: ' . ($r['success'] ? 'refeita' : 'FALHOU'));
        });

        // E de volta à fila: a submissão anterior morreu com o E43.
        $submissoes = AGTSubmission::withoutGlobalScopes()
            ->where('document_type', CreditNote::class)
            ->where('document_id', $nota->id)
            ->get();

        foreach ($submissoes as $s) {
            $s->update([
                'status'        => AGTSubmission::STATUS_PENDING,
                'retry_count'   => 0,
                'error_code'    => null,
                'error_message' => null,
            ]);
        }

        $this->info('Nota acertada. ' . $submissoes->count() . ' submissão(ões) de volta à fila — o despacho reenvia à próxima visita ao sistema.');

        return self::SUCCESS;
    }

    /** A AGT chegou a aceitar esta nota? */
    private function foiAceite(CreditNote $nota): bool
    {
        return AGTSubmission::withoutGlobalScopes()
            ->where('document_type', CreditNote::class)
            ->where('document_id', $nota->id)
            ->whereIn('status', [AGTSubmission::STATUS_VALIDATED])
            ->exists();
    }

    /** O que a factura ainda tem por anular, por linha. */
    private function disponivelPorLinha($factura, CreditNote $excepto): array
    {
        $disponivel = [];

        foreach ($factura->items as $linha) {
            $k = $this->chave($linha->product_id, $linha->description);
            $disponivel[$k] = ($disponivel[$k] ?? 0) + (float) $linha->quantity;
        }

        $outras = CreditNote::withoutGlobalScopes()
            ->where('invoice_id', $factura->id)
            ->where('id', '!=', $excepto->id)
            ->where('status', 'issued')
            ->with('items')
            ->get();

        foreach ($outras as $nota) {
            foreach ($nota->items as $linha) {
                $k = $this->chave($linha->product_id, $linha->description);
                $disponivel[$k] = ($disponivel[$k] ?? 0) - (float) $linha->quantity;
            }
        }

        return $disponivel;
    }

    /**
     * Como se emparelha uma linha da nota com a da factura.
     *
     * Pelo artigo, e pela descrição quando a linha não tem artigo do catálogo —
     * que é o caso das linhas escritas à mão.
     */
    private function chave($artigo, $descricao): string
    {
        return $artigo ? 'p:' . $artigo : 'd:' . mb_strtolower(trim((string) $descricao));
    }

    private function kz($v): string
    {
        return number_format((float) $v, 2, ',', '.') . ' Kz';
    }
}
