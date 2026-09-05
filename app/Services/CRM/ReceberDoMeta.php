<?php

namespace App\Services\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\MetaContact;
use App\Models\CRM\MetaIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transforma o que o Meta entrega (mensagens de WhatsApp/Messenger/Instagram e
 * leads dos anúncios) em Leads + Actividades no CRM.
 *
 * Dedup pelo `meta_contacts`: quem escreve várias vezes é UM lead com várias
 * actividades. Só cria leads se a empresa ligou `criar_leads`.
 */
class ReceberDoMeta
{
    /** Processa um payload inteiro; devolve quantos itens tratou. */
    public function processar(MetaIntegration $mi, array $payload): int
    {
        $objeto = $payload['object'] ?? '';
        $tratados = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            // WhatsApp Cloud API: entry[].changes[].value.messages[]
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? '';
                $value = $change['value'] ?? [];

                if ($field === 'messages' && ! empty($value['messages'])) {
                    $tratados += $this->doWhatsApp($mi, $value);
                } elseif ($field === 'leadgen' && $mi->lead_ads_enabled) {
                    $tratados += $this->doLeadAd($mi, $value);
                }
            }

            // Messenger (object=page) e Instagram (object=instagram): entry[].messaging[]
            foreach ($entry['messaging'] ?? [] as $msg) {
                $canal = $objeto === 'instagram' ? 'instagram' : 'facebook';
                $tratados += $this->doMensagemDirecta($mi, $canal, $msg);
            }
        }

        if ($tratados > 0) {
            $mi->forceFill(['ultimo_evento_em' => now()])->saveQuietly();
        }

        return $tratados;
    }

    /* ── WhatsApp ──────────────────────────────────────────────────── */

    private function doWhatsApp(MetaIntegration $mi, array $value): int
    {
        // Nome do perfil, se veio.
        $nomes = [];
        foreach ($value['contacts'] ?? [] as $c) {
            if (! empty($c['wa_id'])) {
                $nomes[$c['wa_id']] = $c['profile']['name'] ?? null;
            }
        }

        $n = 0;
        foreach ($value['messages'] ?? [] as $m) {
            $waId = $m['from'] ?? null;
            if (! $waId) {
                continue;
            }
            $texto = $this->textoDaMensagem($m);
            $this->registar($mi, 'whatsapp', $waId, $nomes[$waId] ?? null, $waId, 'whatsapp', 'Mensagem WhatsApp', $texto);
            $n++;
        }

        return $n;
    }

    /* ── Messenger / Instagram ─────────────────────────────────────── */

    private function doMensagemDirecta(MetaIntegration $mi, string $canal, array $msg): int
    {
        $psid = $msg['sender']['id'] ?? null;
        if (! $psid || empty($msg['message'])) {
            return 0;
        }
        // Ecos das nossas próprias mensagens não contam.
        if (! empty($msg['message']['is_echo'])) {
            return 0;
        }

        $texto = $msg['message']['text'] ?? '[anexo]';
        $nome = $this->nomeDoPerfil($mi, $canal, $psid);

        $rotulo = $canal === 'instagram' ? 'Instagram' : 'Facebook';
        $this->registar($mi, $canal, $psid, $nome, null, 'nota', "Mensagem {$rotulo}", $texto);

        return 1;
    }

    /* ── Lead Ads ──────────────────────────────────────────────────── */

    private function doLeadAd(MetaIntegration $mi, array $value): int
    {
        $leadgenId = $value['leadgen_id'] ?? null;
        if (! $leadgenId) {
            return 0;
        }

        $dados = $this->buscarLeadAd($mi, $leadgenId);
        $nome = $dados['full_name'] ?? $dados['name'] ?? 'Lead do anúncio';
        $phone = $dados['phone_number'] ?? $dados['phone'] ?? null;
        $email = $dados['email'] ?? null;

        // Lead Ads têm identidade própria (o leadgen_id) — dedup por aí.
        $contacto = MetaContact::firstOrNew([
            'tenant_id' => $mi->tenant_id, 'channel' => 'facebook', 'external_id' => 'lead:'.$leadgenId,
        ]);

        if (! $contacto->lead_id && $mi->criar_leads) {
            $lead = Lead::create([
                'tenant_id' => $mi->tenant_id,
                'name' => $nome,
                'phone' => $phone,
                'email' => $email,
                'source' => 'facebook',
                'status' => 'novo',
            ]);
            $contacto->lead_id = $lead->id;
        }
        $contacto->name = $nome;
        $contacto->phone = $phone;
        $contacto->save();

        if ($contacto->lead_id) {
            $this->criarActividade($mi->tenant_id, $contacto->lead_id, 'nota', 'Lead do anúncio (Facebook/Instagram)',
                collect($dados)->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n"));
        }

        return 1;
    }

    /* ── Núcleo: contacto → lead → actividade ──────────────────────── */

    private function registar(MetaIntegration $mi, string $canal, string $externalId, ?string $nome, ?string $phone, string $tipoActividade, string $assunto, string $texto): void
    {
        $contacto = MetaContact::firstOrNew([
            'tenant_id' => $mi->tenant_id, 'channel' => $canal, 'external_id' => $externalId,
        ]);

        if (! $contacto->lead_id && $mi->criar_leads) {
            $lead = Lead::create([
                'tenant_id' => $mi->tenant_id,
                'name' => $nome ?: ($phone ?: ucfirst($canal).' '.substr($externalId, -6)),
                'phone' => $phone,
                'source' => $canal,
                'status' => 'novo',
            ]);
            $contacto->lead_id = $lead->id;
        }

        if ($nome && ! $contacto->name) {
            $contacto->name = $nome;
        }
        if ($phone && ! $contacto->phone) {
            $contacto->phone = $phone;
        }
        $contacto->save();

        if ($contacto->lead_id) {
            $this->criarActividade($mi->tenant_id, $contacto->lead_id, $tipoActividade, $assunto, $texto);
        }
    }

    private function criarActividade(int $tenantId, int $leadId, string $tipo, string $assunto, string $texto): void
    {
        Activity::create([
            'tenant_id' => $tenantId,
            'lead_id' => $leadId,
            'type' => $tipo,
            'direction' => 'in',   // recebida
            'subject' => $assunto,
            'notes' => mb_substr($texto, 0, 2000),
            'done' => true,
        ]);
    }

    /* ── Ajudas ────────────────────────────────────────────────────── */

    private function textoDaMensagem(array $m): string
    {
        return match ($m['type'] ?? 'text') {
            'text' => $m['text']['body'] ?? '',
            'button' => $m['button']['text'] ?? '[botão]',
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? '[interacção]',
            'image', 'video', 'audio', 'document', 'sticker' => '['.$m['type'].']',
            'location' => '[localização]',
            default => '['.($m['type'] ?? 'mensagem').']',
        };
    }

    /** Nome do perfil via Graph (best-effort; não parte o webhook se falhar). */
    private function nomeDoPerfil(MetaIntegration $mi, string $canal, string $psid): ?string
    {
        $token = $mi->facebook_page_token;
        if (! $token) {
            return null;
        }
        try {
            $r = Http::withToken($token)->timeout(8)
                ->get("https://graph.facebook.com/v21.0/{$psid}", ['fields' => 'name']);

            return $r->successful() ? ($r->json('name') ?: null) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Dados do formulário do Lead Ad via Graph (best-effort). */
    private function buscarLeadAd(MetaIntegration $mi, string $leadgenId): array
    {
        $token = $mi->facebook_page_token;
        if (! $token) {
            return [];
        }
        try {
            $r = Http::withToken($token)->timeout(10)
                ->get("https://graph.facebook.com/v21.0/{$leadgenId}", ['fields' => 'field_data,created_time']);

            if (! $r->successful()) {
                Log::warning('Meta lead ad: Graph recusou', ['id' => $leadgenId, 'status' => $r->status()]);

                return [];
            }
            $out = [];
            foreach ($r->json('field_data') ?? [] as $f) {
                $out[$f['name']] = $f['values'][0] ?? null;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
