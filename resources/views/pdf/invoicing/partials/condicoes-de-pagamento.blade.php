{{-- As condições e políticas de pagamento, no rodapé das proformas e dos
     orçamentos: as do documento e, sem elas, as da empresa (Definições,
     Textos por omissão). Visíveis sempre, com as mudanças de linha. --}}
@php
    $condicoes = trim((string) ($documento->terms ?? ''));

    if ($condicoes === '') {
        $condicoes = trim((string) (\Illuminate\Support\Facades\DB::table('invoicing_settings')
            ->where('tenant_id', $documento->tenant_id)
            ->value('default_terms') ?? ''));
    }
@endphp
@if($condicoes !== '')
    <div class="condicoes-de-pagamento" style="margin-top: 8px; border: 1px solid #cbd5e1; border-radius: 3px; padding: 6px 8px; background: #f8fafc;">
        <div style="font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; color: #1e3a8a; margin-bottom: 3px;">Condições e políticas de pagamento</div>
        <div style="font-size: 8.5px; line-height: 1.45; color: #222;">{!! nl2br(e($condicoes)) !!}</div>
    </div>
@endif
