@php
    $isPurchase = ($direction ?? 'sale') === 'purchase';
    $status = strtolower(trim((string) ($document->agt_status ?? '')));
    $isDraft = ($document->status ?? null) === 'draft'
        || ($document->invoice_status ?? null) === 'N';

    if ($isPurchase) {
        $badge = [
            'label' => __('Responsabilidade do fornecedor'),
            'short' => __('Fornecedor'),
            'icon' => 'fa-building-shield',
            'classes' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'title' => __('Documento recebido. A comunicação desta fatura ao Portal AGT compete ao fornecedor que a emitiu.'),
        ];
    } elseif ($isDraft) {
        $badge = [
            'label' => __('Ainda não emitida'),
            'short' => __('Não emitida'),
            'icon' => 'fa-file-pen',
            'classes' => 'bg-slate-100 text-slate-700 ring-slate-200',
            'title' => __('O documento ainda é rascunho e só poderá ser comunicado à AGT depois da emissão fiscal.'),
        ];
    } else {
        $badge = match ($status) {
            'validated', 'accepted', 'approved', 'success' => [
                'label' => __('Emitida no Portal AGT'),
                'short' => __('Portal AGT'),
                'icon' => 'fa-circle-check',
                'classes' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
                'title' => __('Documento validado e aceite pelo Portal AGT.'),
            ],
            'submitted', 'processing', 'sent' => [
                'label' => __('Enviada — aguarda AGT'),
                'short' => __('Aguarda AGT'),
                'icon' => 'fa-cloud-arrow-up',
                'classes' => 'bg-blue-100 text-blue-800 ring-blue-200',
                'title' => __('Documento enviado ao Portal AGT e ainda a aguardar validação.'),
            ],
            'rejected', 'failed', 'error' => [
                'label' => __('Falhou — reenviar à AGT'),
                'short' => __('Reenviar AGT'),
                'icon' => 'fa-triangle-exclamation',
                'classes' => 'bg-red-100 text-red-800 ring-red-200',
                'title' => __('A comunicação falhou ou foi rejeitada. Corrija a causa indicada na área AGT e reenvie o documento.'),
            ],
            default => [
                'label' => __('Pendente de envio à AGT'),
                'short' => __('Pendente AGT'),
                'icon' => 'fa-clock',
                'classes' => 'bg-amber-100 text-amber-800 ring-amber-200',
                'title' => __('Documento emitido no SOSERP, mas ainda sem confirmação de envio ao Portal AGT.'),
            ],
        };

        $details = [];
        if (!empty($document->agt_reference)) {
            $details[] = __('Referência: :reference', ['reference' => $document->agt_reference]);
        }
        if (!empty($document->agt_request_id)) {
            $details[] = __('Pedido: :request', ['request' => $document->agt_request_id]);
        }
        if (!empty($document->agt_submitted_at)) {
            $submittedAt = $document->agt_submitted_at instanceof \DateTimeInterface
                ? $document->agt_submitted_at->format('d/m/Y H:i')
                : (string) $document->agt_submitted_at;
            $details[] = __('Enviada em: :date', ['date' => $submittedAt]);
        }
        if ($details) {
            $badge['title'] .= ' ' . implode(' | ', $details);
        }
    }
@endphp

<span
    class="inline-flex max-w-[12rem] items-center justify-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold leading-tight ring-1 ring-inset {{ $badge['classes'] }}"
    title="{{ $badge['title'] }}"
    aria-label="{{ $badge['label'] }}"
>
    <i class="fas {{ $badge['icon'] }} shrink-0" aria-hidden="true"></i>
    <span class="hidden 2xl:inline">{{ $badge['label'] }}</span>
    <span class="2xl:hidden">{{ $badge['short'] }}</span>
</span>
