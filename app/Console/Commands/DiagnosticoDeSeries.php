<?php

namespace App\Console\Commands;

use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Tenant;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnóstico das séries documentais de uma empresa.
 *
 * As séries são a peça onde um erro é caro e silencioso: o número sai errado,
 * o documento é emitido na mesma, a AGT recusa-o depois, e a factura já está
 * na mão do cliente. Nenhuma das verificações abaixo é hipotética — todas
 * correspondem a formas concretas de a numeração se estragar.
 *
 * Verifica também a ligação à AGT — ambiente e submissão automática — porque
 * uma série impecável não serve de nada se os documentos não saem daqui.
 *
 * SÓ LÊ. Não corrige nada, não escreve nada, e não mostra dados de clientes —
 * apenas configuração e contagens.
 *
 * Uso:
 *   php artisan series:diagnostico
 *   php artisan series:diagnostico --tenant=farmacia
 *   php artisan series:diagnostico --tenant=12
 */
class DiagnosticoDeSeries extends Command
{
    protected $signature = 'series:diagnostico
                            {--tenant= : id, slug, nome ou parte do nome da empresa}';

    protected $description = 'Verifica as séries documentais à procura de erros e conflitos (só leitura)';

    /** Onde vive o número de cada tipo de documento. */
    private const TABELAS = [
        'invoice'     => ['invoicing_sales_invoices',   'invoice_number'],
        'pos'         => ['invoicing_sales_invoices',   'invoice_number'],
        'proforma'    => ['invoicing_sales_proformas',  'proforma_number'],
        'receipt'     => ['invoicing_receipts',         'receipt_number'],
        'credit_note' => ['invoicing_credit_notes',     'credit_note_number'],
        'debit_note'  => ['invoicing_debit_notes',      'debit_note_number'],
        'advance'     => ['invoicing_advances',         'advance_number'],
        // A coluna é `invoice_number` e não `purchase_number`: com o nome
        // errado o Schema::hasColumn falhava e as séries de compra saltavam a
        // verificação de numeração inteira, sem uma linha a dizê-lo.
        'purchase'    => ['invoicing_purchase_invoices', 'invoice_number'],
        'transport'   => ['invoicing_transport_guides', 'guide_number'],
    ];

    /**
     * Documentos que a AGT espera receber. São estes que ficam por comunicar
     * quando a submissão automática está ligada mas não consegue submeter.
     *
     * As proformas não constam aqui de propósito: não se comunicam, e as
     * tabelas nem sequer têm coluna `agt_status`.
     */
    private const TABELAS_COMUNICAVEIS = [
        'invoicing_sales_invoices',
        'invoicing_credit_notes',
        'invoicing_debit_notes',
        'invoicing_receipts',
        'invoicing_transport_guides',
    ];

    private int $problemas = 0;

    public function handle(): int
    {
        $empresas = $this->empresas();

        if ($empresas->isEmpty()) {
            $this->error('Nenhuma empresa encontrada com esse critério.');

            return self::FAILURE;
        }

        foreach ($empresas as $empresa) {
            $this->diagnosticar($empresa);
        }

        $this->newLine();
        $this->line(str_repeat('=', 62));

        if ($this->problemas === 0) {
            $this->info('Nada a assinalar.');
        } else {
            $this->warn("{$this->problemas} ponto(s) a corrigir.");
        }

        return self::SUCCESS;
    }

    private function empresas()
    {
        $filtro = $this->option('tenant');

        if (!$filtro) {
            return Tenant::orderBy('id')->get();
        }

        if (ctype_digit((string) $filtro)) {
            return Tenant::where('id', (int) $filtro)->get();
        }

        return Tenant::where('slug', 'like', "%{$filtro}%")
            ->orWhere('name', 'like', "%{$filtro}%")
            ->orderBy('id')
            ->get();
    }

    private function diagnosticar(Tenant $empresa): void
    {
        // Leitura directa e não InvoicingSettings::forTenant(): esse faz
        // firstOrCreate e criaria a linha de definições numa empresa que nunca
        // a teve. Este comando não escreve — nem sequer isso.
        $definicoes = InvoicingSettings::where('tenant_id', $empresa->id)->first();

        $series = InvoicingSeries::where('tenant_id', $empresa->id)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get();

        $this->newLine();
        $this->line(str_repeat('=', 62));
        $this->info("EMPRESA #{$empresa->id} — {$empresa->name}");
        $this->line("séries: {$series->count()}   AGT: " . $this->estadoAgt($definicoes));

        // Antes da saída por falta de séries: uma empresa pode ter a submissão
        // automática ligada e séries nenhumas, e continua a ser preciso dizê-lo.
        $this->comunicacaoAgt($empresa, $definicoes);

        if ($series->isEmpty()) {
            $this->aviso('Sem séries nenhumas: nenhum documento pode ser emitido.');

            return;
        }

        $this->prefixosDivergentes($series);
        $this->padraoPorTipo($series, $empresa);
        $this->codigosRepetidos($series);
        $this->numeracaoDessincronizada($series, $empresa);
        $this->porRegistarNaAgt($series, $empresa);
    }

    /**
     * O primeiro token do número TEM de ser o tipo de documento da AGT.
     *
     * A coluna `prefix` é texto livre no formulário (required|max:10) e é ela
     * que entra no número — mas o ecrã mostra o prefixo canónico do catálogo.
     * Quando divergem, o ecrã diz uma coisa e o documento sai com outra.
     *
     * Não é cosmético: a AGT lê o primeiro token para classificar o documento.
     * Está medido neste projecto — 25 documentos com um primeiro token errado
     * foram os 25 recusados com E32, e os 5 com o token certo passaram todos.
     */
    private function prefixosDivergentes($series): void
    {
        foreach ($series as $s) {
            // prefixoDe() e não o array AGT_PREFIXES lido à mão: quem lê o mapa
            // directamente é quem acaba por o copiar, e foram as cópias que
            // divergiram do catálogo em primeiro lugar.
            $canonico = InvoicingSeries::prefixoDe($s->document_type);

            if (!$canonico) {
                // Estar fora do catálogo AGT é normal para um tipo interno e é
                // erro para todos os outros. Sem separar os dois casos, quem lê
                // o relatório não sabe qual deles tem de ir corrigir.
                if (InvoicingSeries::tipoInterno($s->document_type)) {
                    $this->nota(sprintf(
                        "série #%d (%s, %s): tipo interno, não se comunica à AGT — prefixo '%s' fica como está.",
                        $s->id,
                        $s->document_type,
                        $s->series_code,
                        $s->prefix
                    ));
                    continue;
                }

                $this->aviso("série #{$s->id} tem document_type '{$s->document_type}', que não está no catálogo AGT nem é um tipo interno conhecido.");
                continue;
            }

            if ((string) $s->prefix !== $canonico) {
                $this->aviso(sprintf(
                    "série #%d (%s, %s): prefixo gravado '%s' mas o da AGT é '%s'. "
                        . "O número sai '%s' — o ecrã mostra '%s'.",
                    $s->id,
                    $s->document_type,
                    $s->series_code,
                    $s->prefix,
                    $canonico,
                    $s->previewNextNumber(),
                    $canonico
                ));
            }
        }
    }

    /**
     * Uma série padrão por tipo — nem zero, nem duas.
     *
     * E o conflito que mais custa: a padrão está por estrear enquanto outra já
     * vai adiantada. Tudo o que se emitir a seguir passa a sair pela padrão,
     * numa numeração paralela — duas séries do mesmo tipo a andar ao mesmo
     * tempo, o que a AGT não perdoa num SAFT.
     */
    private function padraoPorTipo($series, Tenant $empresa): void
    {
        foreach ($series->groupBy('document_type') as $tipo => $doTipo) {
            $padroes = $doTipo->where('is_default', true);

            if ($padroes->count() > 1) {
                $this->aviso(sprintf(
                    "%s: %d séries marcadas como padrão. Qual delas é usada depende da ordem "
                        . "que a base de dados devolver — ou seja, é imprevisível.",
                    $tipo,
                    $padroes->count()
                ));

                // O DETALHE de cada uma, e não só os nomes.
                //
                // Para decidir qual fica padrão é preciso saber qual tem
                // documentos: desmarcar a que está em uso e deixar uma por
                // estrear recomeça a numeração do zero, ao lado da que já
                // existe. Sem estes números, a escolha é um palpite.
                foreach ($padroes->sortByDesc('next_number') as $p) {
                    $emitidos = max(0, (int) $p->next_number - 1);

                    $this->line(sprintf(
                        '        · %-12s emitidos: %-6d próximo: %-6d AGT: %s',
                        $p->series_code,
                        $emitidos,
                        (int) $p->next_number,
                        $p->agt_series_id ?: '—'
                    ));
                }
            }

            if ($padroes->isEmpty() && $doTipo->count() > 0) {
                $this->aviso("{$tipo}: nenhuma série marcada como padrão.");
            }

            if ($doTipo->count() < 2) {
                continue;
            }

            $padrao = $padroes->first();

            if (!$padrao) {
                continue;
            }

            $emUso = $doTipo->where('id', '!=', $padrao->id)
                ->filter(fn ($s) => (int) $s->next_number > 1);

            if ($padrao->next_number <= 1 && $emUso->isNotEmpty()) {
                $this->aviso(sprintf(
                    "%s: a série padrão '%s' está por estrear (próximo %d), mas '%s' já vai no %d. "
                        . "O que se emitir a seguir sai pela padrão e começa uma segunda numeração em paralelo.",
                    $tipo,
                    $padrao->series_code,
                    $padrao->next_number,
                    $emUso->first()->series_code,
                    $emUso->first()->next_number
                ));
            }
        }
    }

    /** Dois códigos iguais no mesmo tipo tornam o número ambíguo. */
    private function codigosRepetidos($series): void
    {
        foreach ($series->groupBy('document_type') as $tipo => $doTipo) {
            $repetidos = $doTipo->groupBy('series_code')->filter(fn ($g) => $g->count() > 1);

            foreach ($repetidos as $codigo => $g) {
                $this->aviso("{$tipo}: o código de série '{$codigo}' aparece {$g->count()} vezes.");
            }
        }
    }

    /**
     * O próximo número tem de estar à frente do último emitido.
     *
     * Se ficar atrás, a próxima emissão tenta um número que já existe. O índice
     * único apanha-a e o documento falha — mas só na hora de gravar, com o
     * cliente à espera.
     */
    private function numeracaoDessincronizada($series, Tenant $empresa): void
    {
        foreach ($series as $s) {
            [$tabela, $coluna] = self::TABELAS[$s->document_type] ?? [null, null];

            if (!$tabela || !Schema::hasTable($tabela) || !Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            // O número emitido tem a forma "FT FT/000123": o que interessa é a
            // parte depois da barra, e só das linhas desta série.
            $serie = $s->agt_series_id ?: preg_replace('/^SOS/', '', (string) $s->series_code);
            $inicio = trim(($s->prefix ?: '') . ' ' . $serie) . '/';

            $maior = DB::table($tabela)
                ->where('tenant_id', $empresa->id)
                // O prefixo e o código de série entram num LIKE: um '_' vindo
                // deles é um curinga e faria esta série contar números de outra,
                // dando uma colisão de numeração que não existe.
                ->where($coluna, 'like', addcslashes($inicio, '%_\\') . '%')
                ->selectRaw("MAX(CAST(SUBSTRING_INDEX({$coluna}, '/', -1) AS UNSIGNED)) as maior")
                ->value('maior');

            if ($maior === null) {
                continue;
            }

            if ((int) $s->next_number <= (int) $maior) {
                $this->aviso(sprintf(
                    "série #%d (%s, %s): próximo número é %d mas já foi emitido o %d. "
                        . "A emissão seguinte colide com um documento existente.",
                    $s->id,
                    $s->document_type,
                    $s->series_code,
                    $s->next_number,
                    $maior
                ));
            }
        }
    }

    /**
     * Uma série sem código da AGT não devia ter documentos emitidos.
     *
     * O agt_series_id é o elo com o portal. Sem ele, os documentos vão com o
     * nosso código interno, que a AGT não conhece.
     */
    private function porRegistarNaAgt($series, Tenant $empresa): void
    {
        foreach ($series as $s) {
            if ($s->agt_series_id) {
                continue;
            }

            if ((int) $s->next_number <= 1) {
                continue;   // por estrear: ainda vai a tempo de ser registada
            }

            $this->aviso(sprintf(
                "série #%d (%s, %s): sem código AGT registado, mas já emitiu %d documento(s).",
                $s->id,
                $s->document_type,
                $s->series_code,
                (int) $s->next_number - 1
            ));
        }
    }

    /**
     * Uma linha a dizer com que AGT estamos a falar.
     *
     * Vai no cabeçalho porque muda a leitura de tudo o resto: uma série
     * "registada na AGT" em homologação não está registada em lado nenhum que
     * conte, e o mesmo relatório em produção seria outra conversa.
     */
    private function estadoAgt(?InvoicingSettings $definicoes): string
    {
        if (!$definicoes) {
            return 'sem definições';
        }

        $ambiente = $this->ambienteAgt($definicoes);

        return ($ambiente === 'production' ? 'produção' : 'homologação')
            . ', envio automático ' . ($definicoes->agt_auto_submit ? 'LIGADO' : 'desligado');
    }

    /**
     * "Enviar automaticamente" ligado sem nada com que enviar.
     *
     * A submissão precisa do par de chaves RSA do contribuinte e do CAE. Sem
     * eles cada tentativa rebenta, o erro vai para o log e a factura sai na
     * mesma: o utilizador vê o ecrã a prometer comunicação e não há nenhum
     * sinal de que não houve. É por isso que o número de documentos por
     * comunicar cresce durante meses sem ninguém reparar — e é esse número,
     * não o aviso, que mostra o tamanho do problema.
     */
    private function comunicacaoAgt(Tenant $empresa, ?InvoicingSettings $definicoes): void
    {
        if (!$definicoes || !$definicoes->agt_auto_submit) {
            return;
        }

        $ambiente = $this->ambienteAgt($definicoes);
        $emFalta = [];

        // O ambiente TEM de ir explícito: sem ele o AGTKeyStore resolve-o por
        // InvoicingSettings::forTenant(), que faz firstOrCreate e escreveria.
        if (!AGTKeyStore::hasKeyPair($empresa->id, $ambiente)) {
            $emFalta[] = 'chaves RSA (storage/app/private/' . AGTKeyStore::directory($empresa->id, $ambiente) . '/)';
        }

        if (blank($definicoes->agt_eac_code)) {
            $emFalta[] = 'código CAE';
        }

        if ($emFalta) {
            $porComunicar = $this->documentosPorComunicar($empresa);

            $this->aviso(sprintf(
                'envio automático para a AGT ligado, mas falta: %s. As tentativas falham em silêncio — %s.',
                implode(' e ', $emFalta),
                $porComunicar === null
                    ? 'não foi possível contar os documentos por comunicar'
                    : "{$porComunicar} documento(s) emitido(s) sem comunicação"
            ));
        }

        if ($ambiente !== 'production') {
            $this->aviso(
                'envio automático ligado em HOMOLOGAÇÃO: o que for submetido vai para o ambiente de testes da AGT. '
                    . 'Parece comunicado e, para efeitos fiscais, não foi.'
            );
        }
    }

    /** Documentos comunicáveis que nunca chegaram a ter resposta da AGT. */
    private function documentosPorComunicar(Tenant $empresa): ?int
    {
        $total = null;

        foreach (self::TABELAS_COMUNICAVEIS as $tabela) {
            if (!Schema::hasTable($tabela) || !Schema::hasColumn($tabela, 'agt_status')) {
                continue;
            }

            $query = DB::table($tabela)
                ->where('tenant_id', $empresa->id)
                ->where(function ($q) {
                    // Vazio e NULL contam os dois: conforme o caminho por onde
                    // o documento foi criado, um "nunca submetido" fica de uma
                    // forma ou da outra.
                    $q->whereNull('agt_status')->orWhere('agt_status', '');
                });

            if (Schema::hasColumn($tabela, 'deleted_at')) {
                $query->whereNull('deleted_at');
            }

            $total = (int) $total + $query->count();
        }

        return $total;
    }

    private function ambienteAgt(InvoicingSettings $definicoes): string
    {
        return in_array($definicoes->agt_environment, AGTKeyStore::AMBIENTES, true)
            ? $definicoes->agt_environment
            : 'sandbox';
    }

    private function aviso(string $texto): void
    {
        $this->problemas++;
        $this->warn('  ⚠  ' . $texto);
    }

    /** Constatação, não problema: não entra na contagem final. */
    private function nota(string $texto): void
    {
        $this->line('  ·  ' . $texto);
    }
}
