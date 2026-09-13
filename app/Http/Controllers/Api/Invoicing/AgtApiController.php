<?php

namespace App\Http\Controllers\Api\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\AGT\AGTSubmission;
use App\Models\Tenant;
use App\Services\AGT\AmbienteErradoException;
use App\Services\AGT\GestaoAgt;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A CONFIGURAÇÃO AGT, para o ecrã em React.
 *
 * Tudo passa pela `GestaoAgt`, a mesma que o ecrã Livewire usa. Ver é a
 * permissão da AGT; escrever é outra. O super admin da plataforma pode
 * escolher a empresa (`tenant`), como no ecrã de sempre — os outros ficam
 * na sua.
 */
class AgtApiController extends Controller
{
    public function opcoes(Request $request): JsonResponse
    {
        $this->exigirVer($request);

        $superAdmin = (bool) $request->user()?->isPlatformSuperAdmin();

        return response()->json([
            'ambientes' => collect(GestaoAgt::AMBIENTES)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
            'operacoes' => collect(GestaoAgt::OPERACOES)->map(fn ($rotulo, $valor) => ['valor' => $valor, 'rotulo' => $rotulo])->values(),
            'cae' => GestaoAgt::classesCae()->map(fn ($c) => ['codigo' => $c->code, 'descricao' => $c->description])->values(),
            'empresas' => $superAdmin
                ? Tenant::orderBy('name')->get(['id', 'name', 'nif'])->map(fn ($t) => ['id' => $t->id, 'nome' => $t->name, 'nif' => $t->nif])->values()
                : [],
            'permissoes' => ['pode_editar' => $this->podeEditar($request), 'escolhe_empresa' => $superAdmin],
        ]);
    }

    /**
     * Tudo o que o ecrã mostra, para o ambiente pedido — ou para o activo,
     * que é o que interessa a quem entra.
     */
    public function estado(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        [$g, $empresaId] = $this->gestao($request);

        $ambiente = $request->filled('ambiente') ? GestaoAgt::normalizar($request->input('ambiente')) : $g->ambienteActivo();
        $empresa = Tenant::findOrFail($empresaId);

        return response()->json([
            'empresa' => ['id' => $empresa->id, 'nome' => $empresa->name, 'nif' => $empresa->nif],
            'ambiente' => $ambiente,
            'definicoes' => $g->ler(),
            'ambientes' => $g->estadoAmbientes($ambiente),
            'chaves' => $g->chaves($ambiente),
            'em_falta' => $g->emFalta($ambiente),
            'relatorio' => $g->relatorio($ambiente),
            'series' => $g->series($ambiente)->map(fn ($s) => [
                'id' => $s->id,
                'series_code' => $s->series_code,
                'name' => $s->name,
                'document_type' => $s->document_type,
                'agt_series_id' => $s->agt_series_id ?: null,
                'registada' => $s->isAGTRegistered(),
            ])->values(),
            'submissoes' => $g->submissoes($ambiente)->map(fn (AGTSubmission $s) => $this->submissao($s))->values(),
            'logs' => $g->logs($ambiente)->map(fn ($l) => [
                'id' => $l->id,
                'quando' => optional($l->created_at)->format('d/m/Y H:i:s'),
                'service' => $l->service,
                'method' => $l->method,
                'endpoint' => $l->endpoint,
                'response_status' => $l->response_status,
                'response_time' => $l->response_time,
                'success' => (bool) $l->success,
                'error_message' => $l->error_message,
            ])->values(),
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);

        $g->guardar($request->validate(GestaoAgt::regras()));

        return response()->json(['message' => __('Configurações AGT guardadas com sucesso!'), 'data' => $g->ler()]);
    }

    public function activarAmbiente(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production']]);

        try {
            $r = $g->activarAmbiente($d['ambiente']);
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        return response()->json(['message' => $r['mensagem'], 'tipo' => $r['tipo'], 'ambiente_activo' => $g->ambienteActivo()]);
    }

    public function guardarChaves(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);
        $d = $request->validate([
            'ambiente' => ['required', 'in:sandbox,production'],
            'contributorPublicKey' => ['required', 'string', 'max:20000'],
            'contributorPrivateKey' => ['required', 'string', 'max:20000'],
        ]);

        // Uma chave errada sai do serviço como erro de validação, no campo certo.
        return response()->json(['message' => $g->guardarChaves($d['ambiente'], $d['contributorPublicKey'], $d['contributorPrivateKey'])]);
    }

    public function removerChaves(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production']]);

        return response()->json(['message' => $g->removerChaves($d['ambiente'])]);
    }

    public function testarLigacao(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production']]);

        try {
            $r = $g->testarLigacao($d['ambiente']);
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        return response()->json([
            'data' => $r,
            'message' => ($r['success'] ?? false) ? __('Conexão estabelecida com sucesso!') : ($r['error'] ?? 'Falha na conexão'),
        ]);
    }

    public function sincronizarSeries(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production']]);

        try {
            $r = $g->sincronizarSeries($d['ambiente']);
        } catch (DomainException $e) {
            return $this->recusa($e);
        }

        $mensagem = match (true) {
            ($r['total'] ?? 0) === 0 => __('Não existem séries activas pendentes de sincronização.'),
            ($r['failed'] ?? 0) > 0 => "{$r['success']} sincronizada(s); {$r['failed']} falharam.",
            default => "{$r['success']} série(s) sincronizada(s) com sucesso!",
        };

        return response()->json(['data' => $r, 'message' => $mensagem]);
    }

    public function actualizarEstados(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);

        $n = $g->actualizarEstados();

        return response()->json([
            'data' => ['actualizadas' => $n],
            'message' => $n ? "{$n} estado(s) actualizado(s) com a AGT." : 'Estados consultados. Nenhuma alteracao encontrada.',
        ]);
    }

    public function reenviar(Request $request, int $id): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(['ambiente' => ['required', 'in:sandbox,production'], 'repor' => ['nullable', 'boolean']]);

        try {
            $r = ($d['repor'] ?? false) ? $g->reporEReenviar($id, $d['ambiente']) : $g->reenviar($id, $d['ambiente']);
        } catch (DomainException $e) {
            return $this->recusa($e);
        } catch (\Exception $e) {
            return response()->json(['message' => __('Erro: :detalhe', ['detalhe' => $e->getMessage()])], 422);
        }

        $ok = (bool) ($r['success'] ?? false);

        return response()->json([
            'data' => $r,
            'message' => $ok ? __('Documento reenviado com sucesso!') : ($r['error'] ?? 'Falha no reenvio'),
        ], $ok ? 200 : 422);
    }

    public function consultar(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        [$g] = $this->gestao($request);
        $d = $request->validate(GestaoAgt::regrasDaConsulta() + ['ambiente' => ['required', 'in:sandbox,production']]);

        return response()->json(['data' => $g->consultar($d['apiOperation'], $d, $d['ambiente'])]);
    }

    public function contribuinte(Request $request): JsonResponse
    {
        $this->exigirVer($request);
        [$g] = $this->gestao($request);

        return response()->json(['data' => $g->lerContribuinte(), 'permissoes' => ['pode_editar' => $this->podeEditar($request)]]);
    }

    public function guardarContribuinte(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);

        $g->guardarContribuinte($request->validate(GestaoAgt::regrasDoContribuinte()));

        return response()->json(['message' => __('Configuração AGT guardada com sucesso.'), 'data' => $g->lerContribuinte()]);
    }

    /**
     * A CHAVE PRIVADA «DO MODO ANTIGO» do contribuinte, sem ambiente.
     *
     * O ecrã de sempre deixava colá-la aqui e há empresas que ainda assinam
     * com ela. Colar e remover é tudo o que se faz — a chave NUNCA VOLTA na
     * resposta, nem por pedaços: o `lerContribuinte()` diz apenas se está
     * instalada. Uma chave privada que sai numa resposta HTTP fica no registo
     * do navegador, na cache e em qualquer intermediário pelo caminho, e a
     * partir daí qualquer um assina documentos fiscais em nome da empresa.
     *
     * O PEM valida-se no serviço, e o erro sai no campo.
     */
    public function guardarChaveLegado(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);

        $d = $request->validate(['contributor_private_key' => ['required', 'string', 'max:20000']]);

        $g->guardarChaveLegado($d['contributor_private_key']);

        return response()->json([
            'message' => __('Chave privada do contribuinte guardada.'),
            'data' => $g->lerContribuinte(),
        ]);
    }

    public function removerChaveLegado(Request $request): JsonResponse
    {
        $this->exigirEditar($request);
        [$g] = $this->gestao($request);

        $g->removerChaveLegado();

        return response()->json([
            'message' => __('Chave privada do contribuinte removida.'),
            'data' => $g->lerContribuinte(),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    /** @return array{0: GestaoAgt, 1: int} */
    private function gestao(Request $request): array
    {
        $user = $request->user();
        $pedida = $request->integer('tenant') ?: null;
        $id = GestaoAgt::empresaPermitida($user, $pedida) ?? GestaoAgt::empresaPermitida($user, activeTenantId());

        abort_unless($id, 403, __('Empresa não seleccionada ou sem acesso.'));

        /*
         * A PERMISSÃO É A DA EMPRESA ONDE SE MEXE.
         *
         * O `exigirVer`/`exigirEditar` responde pela empresa ACTIVA, e o
         * `?tenant=` escolhe outra: um administrador de A que é só caixa em B
         * trocava as chaves AGT de B, o ambiente e os reenvios (auditoria de
         * segurança de 2026-09-13). Noutra empresa, confere-se lá.
         */
        if ($id !== (int) activeTenantId() && ! $user->isPlatformSuperAdmin()) {
            $activa = getPermissionsTeamId();
            setPermissionsTeamId($id);
            $user->unsetRelation('roles')->unsetRelation('permissions');
            $pode = $user->can($this->permissaoPedida ?? 'invoicing.agt.edit');
            setPermissionsTeamId($activa);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            abort_unless($pode, 403, __('Sem permissão para esta operação nessa empresa.'));
        }

        return [new GestaoAgt($id), $id];
    }

    private function recusa(DomainException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'ambiente_errado' => $e instanceof AmbienteErradoException,
        ], 422);
    }

    private function submissao(AGTSubmission $s): array
    {
        $validada = $s->status === AGTSubmission::STATUS_VALIDATED;

        return [
            'id' => $s->id,
            'quando' => optional($s->created_at)->format('d/m/Y H:i'),
            'document_type_code' => $s->document_type_code,
            'document_number' => $s->document_number,
            'status' => $s->status,
            'agt_reference' => $s->agt_reference,
            'atcud' => $s->atcud,
            'error_code' => $s->error_code,
            'error_message' => $s->error_message,
            'retry_count' => (int) $s->retry_count,
            'pode_reenviar' => !$validada && $s->canRetry(),
            'esgotada' => !$validada && !$s->canRetry(),
        ];
    }

    private function podeEditar(Request $request): bool
    {
        return (bool) ($request->user()?->isPlatformSuperAdmin() || $request->user()?->can('invoicing.agt.edit'));
    }

    /** A permissão que a acção pediu — o `gestao()` confere-a na empresa escolhida. */
    private ?string $permissaoPedida = null;

    private function exigirVer(Request $request): void
    {
        $this->permissaoPedida = 'invoicing.agt.view';

        abort_unless(
            $request->user()?->isPlatformSuperAdmin() || $request->user()?->can('invoicing.agt.view'),
            403,
            __('Sem permissão para esta operação.')
        );
    }

    private function exigirEditar(Request $request): void
    {
        $this->permissaoPedida = 'invoicing.agt.edit';

        abort_unless($this->podeEditar($request), 403, __('Sem permissão para esta operação.'));
    }
}
