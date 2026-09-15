<?php

namespace App\Services\Privacidade;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OS DADOS DE UMA PESSOA, TODOS — o direito de acesso e de portabilidade.
 *
 * RGPD arts. 15.º e 20.º, LGPD art. 18.º, Lei 22/11: a pessoa tem direito a uma
 * cópia do que se guarda sobre ela, num formato que uma máquina leia. Isto
 * junta-o de cada tabela do inventário (InventarioDeDados), com o IP e o
 * browser de cada sessão e entrada — é precisamente o que se pergunta
 * («que IP e que localização guardam de mim?»).
 *
 * O que NÃO entra: os dados que as empresas registam sobre os clientes delas —
 * desses a empresa é a responsável, e os pedidos vão para ela. E nunca a
 * palavra-passe nem o PIN, nem cifrados.
 */
class OsMeusDados
{
    /** O resumo para o ecrã: contagens e as linhas mais recentes. */
    public function resumo(User $u, ?string $sessaoActual = null): array
    {
        return [
            'perfil' => $this->perfil($u),
            'empresas' => $this->empresas($u),
            'sessoes' => $this->sessoes($u, $sessaoActual),
            'entradas' => $this->entradas($u, 10),
            'aparelhos' => $this->aparelhos($u),
            'estatisticas' => $this->estatisticas($u),
            'consentimentos' => Consentimentos::actuais($u),
            'pedidos' => $this->pedidos($u),
        ];
    }

    /** Tudo, para descarregar. */
    public function exportacao(User $u): array
    {
        return [
            'gerado_em' => now()->toIso8601String(),
            'responsavel' => config('privacidade.responsavel'),
            'nota' => __('Cópia dos dados pessoais da sua conta (RGPD arts. 15.º e 20.º; LGPD art. 18.º; Lei 22/11). Não inclui a palavra-passe nem o PIN. Os dados que uma empresa regista sobre os clientes dela pedem-se a essa empresa.'),
            'perfil' => $this->perfil($u),
            'empresas' => $this->empresas($u),
            'sessoes_abertas' => $this->sessoes($u, null),
            'entradas_e_saidas' => $this->entradas($u, 1000),
            'aparelhos_do_pwa' => $this->aparelhos($u),
            'tokens_da_aplicacao' => Schema::hasTable('api_tokens')
                ? DB::table('api_tokens')->where('user_id', $u->id)->get(['name', 'last_used_at', 'created_at'])->map(fn ($l) => (array) $l)->all()
                : [],
            'visitas_ao_site_com_sessao' => $this->visitas($u),
            'consentimentos' => Consentimentos::historico($u),
            'pedidos_de_privacidade' => $this->pedidos($u),
            'pedidos_de_suporte' => Schema::hasTable('support_tickets')
                ? DB::table('support_tickets')->where('user_id', $u->id)->get(['ticket_number', 'subject', 'category', 'status', 'created_at'])->map(fn ($l) => (array) $l)->all()
                : [],
            'inventario' => InventarioDeDados::categorias(),
        ];
    }

    private function perfil(User $u): array
    {
        return [
            'id' => $u->id,
            'nome' => $u->name,
            'email' => $u->email,
            'telefone' => $u->phone,
            'biografia' => $u->bio,
            'fotografia' => (bool) $u->avatar,
            'lingua' => $u->locale,
            'conta_activa' => (bool) $u->is_active,
            'criada_em' => $u->created_at?->toIso8601String(),
            'ultima_entrada' => $u->last_login_at?->toIso8601String(),
            'senha_mudada_em' => $u->last_password_changed ? (string) $u->last_password_changed : null,
            'tem_pin_de_turno' => ! empty($u->pos_pin_hash),
        ];
    }

    private function empresas(User $u): array
    {
        $colunas = ['tenants.id', 'tenants.name', 'tenants.nif', 'tenants.address', 'tenants.city', 'tenants.phone', 'tenants.email', 'tenant_user.joined_at', 'tenant_user.is_active'];
        if (Schema::hasColumn('tenant_user', 'ultimo_acesso_em')) {
            $colunas[] = 'tenant_user.ultimo_acesso_em';
        }

        return DB::table('tenant_user')->join('tenants', 'tenants.id', '=', 'tenant_user.tenant_id')
            ->where('tenant_user.user_id', $u->id)->whereNull('tenants.deleted_at')
            ->get($colunas)
            ->map(fn ($l) => [
                'id' => (int) $l->id,
                'nome' => $l->name,
                'nif' => $l->nif,
                'morada' => trim(implode(', ', array_filter([$l->address, $l->city]))) ?: null,
                'telefone' => $l->phone,
                'email' => $l->email,
                'entrou_em' => $l->joined_at,
                'activo' => (bool) $l->is_active,
                'ultimo_acesso' => $l->ultimo_acesso_em ?? null,
            ])->all();
    }

    private function sessoes(User $u, ?string $actual): array
    {
        if (! Schema::hasTable('sessions')) {
            return [];
        }

        return DB::table('sessions')->where('user_id', $u->id)->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn ($s) => [
                'esta' => $actual !== null && hash_equals((string) $s->id, $actual),
                'ip' => $s->ip_address,
                'aparelho' => $this->aparelho((string) $s->user_agent),
                'browser' => mb_strimwidth((string) $s->user_agent, 0, 200, '…'),
                'ultima_actividade' => date('c', (int) $s->last_activity),
            ])->all();
    }

    private function entradas(User $u, int $limite): array
    {
        if (! Schema::hasTable('audit_trail')) {
            return [];
        }

        return DB::table('audit_trail')->where('user_id', $u->id)
            ->whereIn('event', ['login', 'logout'])
            ->orderByDesc('id')->limit($limite)
            ->get(['event', 'ip_address', 'user_agent', 'tenant_id', 'created_at'])
            ->map(fn ($l) => [
                'evento' => $l->event === 'login' ? __('Entrada') : __('Saída'),
                'ip' => $l->ip_address,
                'aparelho' => $this->aparelho((string) $l->user_agent),
                'quando' => (string) $l->created_at,
            ])->all();
    }

    private function aparelhos(User $u): array
    {
        if (! Schema::hasTable('pwa_devices') || ! Schema::hasColumn('pwa_devices', 'user_id')) {
            return [];
        }

        return DB::table('pwa_devices')->where('user_id', $u->id)->get()
            ->map(fn ($l) => [
                'plataforma' => $l->platform ?? null,
                'aparelho' => $this->aparelho((string) ($l->user_agent ?? '')),
                'visto_pela_primeira_vez' => $l->first_seen_at ?? $l->created_at ?? null,
                'visto_pela_ultima_vez' => $l->last_seen_at ?? $l->updated_at ?? null,
            ])->all();
    }

    private function estatisticas(User $u): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return ['eventos' => 0];
        }

        $base = DB::table('analytics_events')->where('user_id', $u->id);

        return [
            'eventos' => (clone $base)->count(),
            'primeiro' => (clone $base)->min('created_at'),
            'ultimo' => (clone $base)->max('created_at'),
            'paises' => (clone $base)->whereNotNull('country')->distinct()->pluck('country')->values()->all(),
            'cidades' => (clone $base)->whereNotNull('city')->distinct()->pluck('city')->values()->all(),
        ];
    }

    private function visitas(User $u): array
    {
        if (! Schema::hasTable('analytics_events')) {
            return [];
        }

        return DB::table('analytics_events')->where('user_id', $u->id)->orderByDesc('id')->limit(2000)
            ->get(['type', 'path', 'referrer', 'ip', 'country', 'city', 'device_type', 'browser', 'os', 'language', 'created_at'])
            ->map(fn ($l) => (array) $l)->all();
    }

    private function pedidos(User $u): array
    {
        if (! Schema::hasTable('pedidos_de_privacidade')) {
            return [];
        }

        return DB::table('pedidos_de_privacidade')->where('user_id', $u->id)->orderByDesc('id')
            ->get(['id', 'tipo', 'mensagem', 'estado', 'resposta', 'prazo_em', 'respondido_em', 'created_at'])
            ->map(fn ($l) => (array) $l)->all();
    }

    /** «Chrome no Windows» diz mais a uma pessoa do que 200 caracteres de user agent. */
    private function aparelho(string $ua): string
    {
        if ($ua === '') {
            return __('Desconhecido');
        }

        $browser = collect(['Edg' => 'Edge', 'OPR' => 'Opera', 'Firefox' => 'Firefox', 'Chrome' => 'Chrome', 'Safari' => 'Safari'])
            ->first(fn ($nome, $agulha) => str_contains($ua, $agulha)) ?? __('Browser');
        $sistema = collect(['Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'])
            ->first(fn ($nome, $agulha) => str_contains($ua, $agulha)) ?? __('outro sistema');

        return __(':browser em :sistema', ['browser' => $browser, 'sistema' => $sistema]);
    }
}
