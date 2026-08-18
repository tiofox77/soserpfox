<?php

namespace App\Services\Agent;

use App\Models\AgentMessage;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use App\Services\SmsService;
use App\Support\AgenteAutenticado;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Envio de seguimento pelo agente.
 *
 * Comunicação externa é irreversível: uma vez enviada, não se chama de
 * volta. Por isso tudo aqui é conservador — tectos diários, arrefecimento
 * por empresa, silêncio nocturno, e a linha de registo criada ANTES de se
 * falar com o fornecedor. Se não se conseguir registar, não se envia.
 */
class EnvioDeFollowUp
{
    public function __construct(
        private DestinatariosPermitidos $destinatarios,
    ) {
    }

    /**
     * Porque é que este envio não pode acontecer. null = pode.
     *
     * Corre igual no preview e no envio real, para o que o agente vê ser
     * exactamente o que vai suceder.
     */
    public function porqueNaoPode(
        AgenteAutenticado $agente,
        Tenant $tenant,
        string $canal,
        string $template
    ): ?string {
        $modelos = config('agent.followup.templates', []);

        if (!isset($modelos[$template])) {
            return "O modelo '{$template}' não está na lista permitida.";
        }

        if (!in_array($canal, $modelos[$template]['canais'], true)) {
            return "O modelo '{$template}' não pode ser enviado por {$canal}.";
        }

        if ($silencio = $this->emSilencio()) {
            return $silencio;
        }

        $hoje = now()->toDateString();

        $tectoDoCanal = $canal === 'sms'
            ? (int) config('agent.followup.sms_por_dia', 10)
            : (int) config('agent.followup.email_por_dia', 30);

        $enviadosHoje = AgentMessage::where('agent_token_id', $agente->id())
            ->where('canal', $canal)
            ->whereDate('dia', $hoje)
            ->where('estado', '!=', AgentMessage::FALHOU)
            ->count();

        if ($enviadosHoje >= $tectoDoCanal) {
            return "Tecto diário de {$canal} atingido ({$tectoDoCanal}).";
        }

        // Arrefecimento: o mesmo modelo, para a mesma empresa, não se repete.
        $horas = (int) config('agent.followup.arrefecimento_horas', 72);

        $recente = AgentMessage::where('tenant_id', $tenant->id)
            ->where('template_slug', $template)
            ->where('estado', '!=', AgentMessage::FALHOU)
            ->where('created_at', '>=', now()->subHours($horas))
            ->exists();

        if ($recente) {
            return "Esta empresa já recebeu '{$template}' nas últimas {$horas} horas.";
        }

        // E um limite global de insistência, seja qual for o modelo.
        $maximo = (int) config('agent.followup.maximo_por_empresa_30d', 3);

        $ultimos30 = AgentMessage::where('tenant_id', $tenant->id)
            ->where('estado', '!=', AgentMessage::FALHOU)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($ultimos30 >= $maximo) {
            return "Esta empresa já recebeu {$ultimos30} mensagens em 30 dias (máximo {$maximo}).";
        }

        return null;
    }

    /** Janela de silêncio: ninguém quer SMS do fornecedor às 23h. */
    private function emSilencio(): ?string
    {
        $cfg = config('agent.followup.silencio', []);
        $fuso = $cfg['fuso'] ?? 'Africa/Luanda';

        $agora = Carbon::now($fuso);

        if (($cfg['domingo'] ?? true) && $agora->isSunday()) {
            return 'Não se enviam mensagens ao domingo.';
        }

        $inicio = (int) ($cfg['inicio'] ?? 21);
        $fim    = (int) ($cfg['fim'] ?? 7);
        $hora   = (int) $agora->format('G');

        if ($hora >= $inicio || $hora < $fim) {
            return "Fora de horas ({$agora->format('H:i')} em {$fuso}); "
                 . "envia-se entre as {$fim}:00 e as {$inicio}:00.";
        }

        return null;
    }

    /**
     * Envia mesmo.
     *
     * A reserva em agent_messages vem primeiro, protegida pelo índice único
     * — se duas chamadas passarem os tectos ao mesmo tempo, só uma envia.
     */
    public function enviar(
        AgenteAutenticado $agente,
        Tenant $tenant,
        string $canal,
        string $template,
        string $handle,
        array $variaveis,
        string $motivo,
        ?string $idempotencyKey = null
    ): array {
        $contacto = $this->destinatarios->resolver($tenant, $handle);

        if (!$contacto) {
            return ['ok' => false, 'erro' => "Destinatário '{$handle}' não existe nesta empresa."];
        }

        $destino = $canal === 'sms' ? $contacto['telefone'] : $contacto['email'];

        if (!$destino) {
            return ['ok' => false, 'erro' => "O destinatário não tem {$canal}."];
        }

        try {
            $registo = AgentMessage::create([
                'agent_token_id'         => $agente->id(),
                'tenant_id'              => $tenant->id,
                'canal'                  => $canal,
                'template_slug'          => $template,
                'destinatario_handle'    => $handle,
                'destinatario_mascarado' => $contacto['mascarado'],
                'estado'                 => AgentMessage::RESERVADO,
                'motivo'                 => $motivo,
                'idempotency_key'        => $idempotencyKey,
                'dia'                    => now()->toDateString(),
            ]);
        } catch (UniqueConstraintViolationException $e) {
            return ['ok' => false, 'erro' => 'Já foi enviada hoje uma mensagem igual a esta empresa.'];
        }

        try {
            if ($canal === 'email') {
                EmailTemplate::sendEmail($template, $destino, $variaveis, $tenant->id);
            } else {
                app(SmsService::class)->send(
                    $destino,
                    $this->textoDoSms($template, $variaveis),
                    'followup',
                    null,
                    $tenant->id
                );
            }

            $registo->update(['estado' => AgentMessage::ENVIADO]);

            return [
                'ok'          => true,
                'mensagem_id' => $registo->id,
                'destinatario' => $contacto['mascarado'],
            ];
        } catch (\Throwable $e) {
            $registo->update([
                'estado' => AgentMessage::FALHOU,
                'erro'   => mb_substr($e->getMessage(), 0, 1000),
            ]);

            return ['ok' => false, 'erro' => 'O envio falhou: ' . $e->getMessage()];
        }
    }

    /**
     * Texto do SMS, a partir do modelo permitido.
     * O agente não escreve texto — escolhe o modelo e dá as variáveis.
     */
    public function textoDoSms(string $template, array $variaveis): string
    {
        $textos = [
            'followup_teste_a_terminar' =>
                'Ola :nome_empresa, o seu periodo de teste do SOS ERP termina em :dias dias. '
                . 'Fale connosco para continuar: soserp.vip',
            'followup_plano_expirado' =>
                'Ola :nome_empresa, o seu plano do SOS ERP expirou e o acesso vai ser limitado. '
                . 'Renove em soserp.vip',
        ];

        $texto = $textos[$template] ?? '';

        foreach ($variaveis as $chave => $valor) {
            $texto = str_replace(':' . $chave, (string) $valor, $texto);
        }

        return $texto;
    }
}
