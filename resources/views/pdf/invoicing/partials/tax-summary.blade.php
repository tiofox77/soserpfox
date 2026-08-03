{{--
    Resumo de Impostos — calculado a partir das LINHAS do documento.

    Antes estava fixo em "IVA 14%", pelo que uma empresa em regime de isenção
    (ou com taxas 7%/5%/0%) imprimia um resumo falso e nunca mostrava o motivo
    de isenção — que é obrigatório na AGT quando não há imposto.

    Espera: $doc (documento com ->items)
--}}
@php
    $__items = collect($doc->items ?? []);

    // Agrupar por taxa: cada taxa é uma linha do resumo
    $__groups = $__items->groupBy(fn($i) => (string) (float) ($i->tax_rate ?? 0));

    // Motivo de isenção a mostrar (primeiro código encontrado nas linhas isentas)
    $__exemptItem = $__items->first(fn($i) => (float) ($i->tax_rate ?? 0) <= 0 && !empty($i->tax_exemption_code));
    $__exemptCode = $__exemptItem->tax_exemption_code ?? null;
    $__exemptReason = $__exemptItem->tax_exemption_reason ?? null;

    // Fallback: motivo do imposto por omissão do tenant
    if (!$__exemptCode && $__items->contains(fn($i) => (float) ($i->tax_rate ?? 0) <= 0)) {
        $__defTax = \App\Models\Invoicing\Tax::where('tenant_id', $doc->tenant_id)
            ->where('is_default', true)->first();
        $__exemptCode = $__defTax->exemption_code ?? null;
        $__exemptReason = $__defTax->exemption_reason ?? null;
    }

    // ── IEC e Imposto de Selo ─────────────────────────────────────────────
    // O documento impresso TEM de mostrar todos os impostos que foram
    // declarados à AGT. Sem isto uma factura com IEC ou Selo saía impressa a
    // mostrar só o IVA, com um total que o cliente não conseguia reconciliar.
    $__extras = collect();
    if ($__items->isNotEmpty() && $__items->first() instanceof \Illuminate\Database\Eloquent\Model) {
        $__extras = \App\Models\Invoicing\LineTax::where('line_type', get_class($__items->first()))
            ->whereIn('line_id', $__items->pluck('id'))
            ->get()
            ->groupBy(fn($t) => $t->tax_type . '|' . $t->tax_percentage . '|' . $t->verba_no);
    }

    // Região fiscal: Cabinda tem regime próprio e deve constar no documento
    $__regiao = $__items->first()->tax_country_region ?? 'AO';

    // Retenção na fonte / cativação
    $__retencoes = collect();
    try {
        $__retencoes = \Illuminate\Support\Facades\DB::table('invoicing_withholding_taxes')
            ->where('document_type', get_class($doc))
            ->where('document_id', $doc->id)
            ->get();
    } catch (\Throwable $e) {
        // tabela ausente nesta instalação
    }
@endphp

<tbody>
    @forelse($__groups as $__rate => $__lines)
        @php
            $__rate = (float) $__rate;
            $__base = $__lines->sum(fn($i) => (float) ($i->subtotal ?? 0) - (float) ($i->discount_amount ?? 0));
            $__tax  = $__lines->sum(fn($i) => (float) ($i->tax_amount ?? 0));
        @endphp
        <tr>
            <td>{{ $__rate > 0 ? 'IVA' : 'Isento de IVA' }}</td>
            <td>{{ rtrim(rtrim(number_format($__rate, 2, ',', ''), '0'), ',') }}%</td>
            <td class="currency">{{ number_format($__base, 2, ',', '.') }}</td>
            <td class="currency">{{ number_format($__tax, 2, ',', '.') }}</td>
        </tr>
    @empty
        <tr>
            <td>—</td>
            <td>0%</td>
            <td class="currency">{{ number_format($doc->subtotal ?? 0, 2, ',', '.') }}</td>
            <td class="currency">{{ number_format($doc->tax_amount ?? 0, 2, ',', '.') }}</td>
        </tr>
    @endforelse

    {{-- IEC e Imposto de Selo: um por taxa/verba --}}
    @foreach($__extras as $__grupo)
        @php
            $__e = $__grupo->first();
            $__valor = $__grupo->sum(fn($t) => (float) $t->tax_amount);
            $__rotulo = $__e->tax_type === 'IEC'
                ? 'IEC' . ($__e->pautal_code ? ' (cód. ' . $__e->pautal_code . ')' : '')
                : 'Imposto de Selo' . ($__e->verba_no ? ' (verba ' . $__e->verba_no . ')' : '');
        @endphp
        <tr>
            <td>{{ $__rotulo }}</td>
            <td>{{ (float) $__e->tax_percentage > 0
                    ? rtrim(rtrim(number_format((float) $__e->tax_percentage, 2, ',', ''), '0'), ',') . '%'
                    : '—' }}</td>
            <td class="currency">—</td>
            <td class="currency">{{ number_format($__valor, 2, ',', '.') }}</td>
        </tr>
    @endforeach

    @if($__exemptCode)
        {{-- Motivo de isenção: obrigatório quando o documento não liquida imposto --}}
        <tr>
            <td colspan="4" style="font-size:9px; padding-top:4px;">
                <strong>Motivo de isenção:</strong> {{ $__exemptCode }}{{ $__exemptReason ? ' — ' . $__exemptReason : '' }}
            </td>
        </tr>
    @endif

    @if($__regiao === 'AO-CAB')
        <tr>
            <td colspan="4" style="font-size:9px; padding-top:4px;">
                <strong>Região fiscal:</strong> Cabinda (AO-CAB) — regime próprio
            </td>
        </tr>
    @endif

    @foreach($__retencoes as $__ret)
        {{-- Retenção reduz o valor a receber: o cliente tem de a ver no documento --}}
        <tr>
            <td colspan="2" style="font-size:9px;">
                <strong>Retenção {{ $__ret->withholding_tax_type }}</strong>
                @if((float) $__ret->withholding_tax_percentage > 0)
                    ({{ rtrim(rtrim(number_format((float) $__ret->withholding_tax_percentage, 2, ',', ''), '0'), ',') }}%)
                @endif
            </td>
            <td class="currency">—</td>
            <td class="currency">-{{ number_format((float) $__ret->withholding_tax_amount, 2, ',', '.') }}</td>
        </tr>
    @endforeach
</tbody>
