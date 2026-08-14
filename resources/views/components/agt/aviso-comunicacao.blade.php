{{--
    Aviso: a empresa julga que está a comunicar à AGT, e não está.

    Ligar o "enviar automaticamente" não chega. Sem chaves RSA instaladas o
    documento não pode sequer ser assinado, sem CAE a AGT recusa a submissão, e
    em homologação o que segue fica no ambiente de testes. Em qualquer destes
    casos o AutoSubmissao apanha a falha, escreve-a no log e devolve o controlo
    — a venda faz-se, o documento imprime-se, e ninguém no ecrã fica a saber.

    Isto não é hipotético: uma farmácia tinha 1108 facturas emitidas e nenhuma
    comunicada, com o interruptor ligado e nada no ecrã que o dissesse. Daí o
    aviso ser vermelho, dizer o que está a acontecer NA PRÁTICA e trazer a
    contagem real dos documentos por comunicar.

    Componente partilhado de propósito: o mapa de prefixos AGT já anda copiado
    por quatro sítios e diverge; esta verificação não vai pelo mesmo caminho.
--}}
@php
    $tenantId = activeTenantId();
    $definicoes = $tenantId ? \App\Models\Invoicing\InvoicingSettings::forTenant($tenantId) : null;
    $ligado = $definicoes && (bool) $definicoes->agt_auto_submit;

    $motivos = [];
    // Distingue "não sai nada" de "sai, mas para o ambiente de testes": são
    // dois problemas com gravidades diferentes e o texto não pode confundi-los.
    $bloqueado = false;

    if ($ligado) {
        // Só a PRESENÇA do par de chaves. O conteúdo é material criptográfico
        // do contribuinte e não tem nada que aparecer num ecrã.
        if (!\App\Services\AGT\AGTKeyStore::hasKeyPair($tenantId)) {
            $bloqueado = true;
            $motivos[] = __('Não há chaves RSA instaladas para esta empresa. Sem elas o documento não pode ser assinado, e a AGT só aceita documentos assinados.');
        }

        if (blank($definicoes->agt_eac_code)) {
            $bloqueado = true;
            $motivos[] = __('O código CAE não está definido. A AGT exige-o em todas as submissões e recusa as que chegam sem ele.');
        }

        if (($definicoes->agt_environment ?: 'sandbox') !== 'production') {
            $motivos[] = __('O ambiente activo é o de homologação (testes). O que for enviado fica no ambiente de testes da AGT e não conta como documento comunicado.');
        }
    }
@endphp

@if($ligado && !empty($motivos))
    @php
        // A contagem só se pede quando já há motivo para avisar — não se
        // carrega a base de dados de todas as empresas para não mostrar nada.
        $emitidas = null;
        $porComunicar = null;

        if (\Illuminate\Support\Facades\Schema::hasTable('invoicing_sales_invoices')
            && \Illuminate\Support\Facades\Schema::hasColumn('invoicing_sales_invoices', 'agt_submitted_at')) {
            $base = \App\Models\Invoicing\SalesInvoice::where('tenant_id', $tenantId);
            $emitidas = (clone $base)->count();
            $porComunicar = (clone $base)->whereNull('agt_submitted_at')->count();
        }
    @endphp

    <div class="mb-6 rounded-2xl border-2 {{ $bloqueado ? 'border-red-300 bg-red-50' : 'border-amber-300 bg-amber-50' }} p-5">
        <div class="flex items-start gap-4">
            <i class="fas fa-triangle-exclamation text-2xl {{ $bloqueado ? 'text-red-600' : 'text-amber-600' }} mt-0.5"></i>
            <div class="min-w-0 flex-1">
                <h3 class="font-bold {{ $bloqueado ? 'text-red-900' : 'text-amber-900' }}">
                    @if($bloqueado)
                        {{ __('Os documentos NÃO estão a ser comunicados à AGT') }}
                    @else
                        {{ __('Os documentos estão a ir para o ambiente de testes da AGT') }}
                    @endif
                </h3>

                <p class="mt-1 text-sm {{ $bloqueado ? 'text-red-800' : 'text-amber-800' }}">
                    @if($bloqueado)
                        {{ __('O envio automático está ligado, por isso o sistema tenta enviar cada documento. As tentativas falham, ficam apenas no registo do servidor, e quem emite não vê nada — a venda faz-se e o documento imprime-se na mesma.') }}
                    @else
                        {{ __('O envio automático está ligado e os documentos seguem — mas para o ambiente de testes. Perante a AGT, continuam por comunicar.') }}
                    @endif
                </p>

                @if($porComunicar > 0)
                <p class="mt-2 text-sm font-bold {{ $bloqueado ? 'text-red-900' : 'text-amber-900' }}">
                    {{ __('Nesta empresa: :emitidas facturas emitidas, :porComunicar por comunicar.', [
                        'emitidas' => number_format($emitidas, 0, ',', ' '),
                        'porComunicar' => number_format($porComunicar, 0, ',', ' '),
                    ]) }}
                </p>
                @endif

                <ul class="mt-3 space-y-1.5 text-sm {{ $bloqueado ? 'text-red-800' : 'text-amber-800' }}">
                    @foreach($motivos as $motivo)
                    <li class="flex items-start gap-2">
                        <i class="fas fa-circle text-[5px] mt-2 flex-shrink-0"></i>
                        <span>{{ $motivo }}</span>
                    </li>
                    @endforeach
                </ul>

                @can('invoicing.agt.view')
                <a href="{{ route('invoicing.agt-credentials') }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-bold text-white transition {{ $bloqueado ? 'bg-red-600 hover:bg-red-700' : 'bg-amber-600 hover:bg-amber-700' }}">
                    <i class="fas fa-shield-alt"></i>
                    {{ __('Corrigir a configuração AGT') }}
                </a>
                @endcan
            </div>
        </div>
    </div>
@endif
