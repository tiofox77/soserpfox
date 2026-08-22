<?php

namespace App\Services\Agent;

use App\Models\AgentMessage;
use App\Models\Tenant;
use App\Services\SmsService;
use App\Support\AgenteAutenticado;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/** Comunicação livre do agente, limitada a contactos que o SOSERP resolve. */
class EnvioLivreDoAgente
{
    public function __construct(private DestinatariosPermitidos $destinatarios, private SmsService $sms) {}

    public function enviar(AgenteAutenticado $agente, Tenant $tenant, string $canal, string $handle,
        string $mensagem, string $motivo, ?string $assunto, ?string $idempotencyKey): array
    {
        $contacto = $this->destinatarios->resolver($tenant, $handle);
        if (!$contacto) return ['ok' => false, 'erro' => 'Destinatário não autorizado nesta empresa.', 'status' => 422];
        $destino = $canal === 'sms' ? ($contacto['telefone'] ?? null) : ($contacto['email'] ?? null);
        if (!$destino) return ['ok' => false, 'erro' => "O destinatário não tem {$canal} configurado.", 'status' => 422];

        if ($canal === 'sms' && $this->foraDeHoras()) {
            return ['ok' => false, 'erro' => 'SMS livre bloqueado pelo horário de silêncio (21:00–07:00, Luanda).', 'status' => 422];
        }
        $limite = (int) config('agent.followup.' . ($canal === 'sms' ? 'sms_por_dia' : 'email_por_dia'));
        $usados = AgentMessage::where('agent_token_id', $agente->id())->where('canal', $canal)
            ->whereDate('dia', today())->where('estado', '!=', AgentMessage::FALHOU)->count();
        if ($usados >= $limite) return ['ok' => false, 'erro' => "Limite diário de {$canal} atingido.", 'status' => 429];

        $slug = 'livre_' . substr(hash('sha256', $canal . '|' . $handle . '|' . $mensagem), 0, 20);
        try {
            $registo = AgentMessage::create([
                'agent_token_id' => $agente->id(), 'tenant_id' => $tenant->id,
                'canal' => $canal, 'template_slug' => $slug, 'destinatario_handle' => $handle,
                'destinatario' => $destino, 'estado' => AgentMessage::RESERVADO,
                'motivo' => $motivo, 'idempotency_key' => $idempotencyKey, 'dia' => today(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return ['ok' => false, 'erro' => 'Esta mensagem já foi enviada hoje para esta empresa.', 'status' => 409];
        }

        try {
            if ($canal === 'sms') {
                $r = $this->sms->send($destino, $mensagem, 'agent_free', $agente->responsavelId(), $tenant->id);
                if (!($r['success'] ?? false)) throw new \RuntimeException($r['error'] ?? 'Gateway recusou o SMS.');
            } else {
                Mail::raw($mensagem, function ($mail) use ($destino, $assunto) {
                    $mail->to($destino)->subject((string) $assunto);
                });
            }
            $registo->update(['estado' => AgentMessage::ENVIADO]);
            return ['ok' => true, 'mensagem_id' => $registo->id, 'canal' => $canal,
                'destinatario_handle' => $handle, 'destinatario' => $destino];
        } catch (\Throwable $e) {
            $registo->update(['estado' => AgentMessage::FALHOU, 'erro' => mb_substr($e->getMessage(), 0, 1000)]);
            return ['ok' => false, 'erro' => 'O envio falhou: ' . $e->getMessage(), 'status' => 502];
        }
    }

    private function foraDeHoras(): bool
    {
        $cfg = config('agent.followup.silencio');
        $agora = Carbon::now($cfg['fuso'] ?? 'Africa/Luanda');
        $hora = (int) $agora->format('G');
        return (($cfg['domingo'] ?? true) && $agora->isSunday())
            || $hora >= (int) ($cfg['inicio'] ?? 21) || $hora < (int) ($cfg['fim'] ?? 7);
    }
}
