<?php

namespace App\Http\Controllers\Api\Casca;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditRecorder;
use App\Services\Casca\MensagensParaOUtilizador;
use App\Services\Casca\NotificacoesDoSistema;
use App\Services\Casca\PaginaInicial;
use App\Services\Casca\PrazoDaSubscricao;
use App\Services\Plataforma\Personificacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A CASCA — o que vive no topo de todas as páginas: a empresa activa e a troca
 * de empresa, o contador da subscrição, o sino das notificações, e as
 * mensagens do dono da plataforma.
 *
 * Eram seis componentes Livewire no layout. Passam a pequenos ecrãs React que
 * falam com isto. Só pede sessão (`auth`): o topo tem de funcionar também
 * quando a subscrição acabou, que é precisamente quando o contador interessa.
 */
class CascaApiController extends Controller
{
    public function __construct(
        private PrazoDaSubscricao $prazo,
        private NotificacoesDoSistema $sistema,
        private MensagensParaOUtilizador $mensagens,
        private Personificacao $personificacao,
    ) {}

    /** A empresa activa, as outras a que pertence, e o prazo — numa ida só. */
    public function topo(Request $request): JsonResponse
    {
        $user = $request->user();
        $empresas = $user->tenants()->orderBy('name')->get(['tenants.id', 'tenants.name', 'tenants.nif']);
        $activa = $user->activeTenant();

        // Os papéis numa consulta, e não um Role::find por empresa.
        $papeis = DB::table('roles')->whereIn('id', $empresas->pluck('pivot.role_id')->filter()->unique())->pluck('name', 'id');

        $contagem = $empresas->count();
        $maximo = $user->is_super_admin ? null : $user->getMaxCompaniesLimit();

        return response()->json([
            'empresa' => [
                'activa' => $activa ? ['id' => $activa->id, 'nome' => $activa->name] : null,
                'empresas' => $empresas->map(fn ($e) => [
                    'id' => $e->id,
                    'nome' => $e->name,
                    'nif' => $e->nif,
                    'papel' => $e->pivot?->role_id ? ($papeis[$e->pivot->role_id] ?? null) : null,
                ]),
                'contagem' => $contagem,
                'maximo' => $maximo,
                'excedido' => $maximo !== null && $contagem > $maximo,
            ],
            'prazo' => $this->prazo->para($user),
            // A faixa da personificação: quem, onde, desde quando e até quando.
            // Null fora dela — e é assim que a faixa sabe que tem de sair.
            'personificacao' => $this->personificacao->estado(),
        ]);
    }

    /**
     * VOLTAR À PLATAFORMA — o botão da faixa da personificação.
     *
     * Quem chama é, para o sistema, a pessoa da empresa; o serviço confirma que
     * havia mesmo uma personificação e que o admin ainda pode voltar.
     */
    public function sairDaPersonificacao(Request $request): JsonResponse
    {
        // Tem de se saber ANTES de sair: o sair apaga as chaves. E o revendedor
        // não tem conta de admin a que voltar — o `null` dele é a saída normal,
        // não a conta que já não pode voltar.
        $doRevendedor = $this->personificacao->doRevendedor();
        $admin = $this->personificacao->sair($request);

        return response()->json([
            'message' => match (true) {
                $doRevendedor => __('Voltou ao seu portal de revendedor.'),
                $admin !== null => __('Voltou à plataforma.'),
                default => __('A sua conta já não pode voltar à plataforma. Inicie sessão de novo.'),
            },
            'seguir_para' => $this->personificacao->destino(),
        ]);
    }

    /**
     * Mudar de empresa. O limite do plano trava criar empresas novas — as que
     * já são dele continuam acessíveis para consulta.
     */
    public function trocarDeEmpresa(Request $request, int $id, AuditRecorder $auditoria): JsonResponse
    {
        $user = $request->user();
        $anterior = activeTenantId();

        if (! $user->switchTenant($id)) {
            // Tentar entrar numa empresa a que não se pertence é sondagem, não
            // engano: fica na trilha da empresa onde a pessoa está.
            $auditoria->acto('empresa.troca_recusada', null, ['tentou_entrar_em' => $id]);

            return response()->json(['message' => __('Você não tem permissão para acessar esta empresa!')], 403);
        }

        // Fica na trilha da empresa em que se ENTROU.
        $auditoria->acto('empresa.trocada', $id, ['de' => $anterior]);

        // O contexto inteiro mudou: o ecrã aterra numa página NOVA, em casa,
        // onde nada depende da empresa de onde se veio.
        return response()->json(['message' => __('Empresa activa alterada.'), 'ir_para' => route('home')]);
    }

    public function notificacoes(Request $request): JsonResponse
    {
        $request->validate(['so_por_ler' => ['nullable', 'boolean']]);

        $user = $request->user();
        $soPorLer = $request->boolean('so_por_ler', true);

        $daBase = $user->notifications()
            ->when($soPorLer, fn ($q) => $q->whereNull('read_at'))
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'tipo' => $n->data['type'] ?? 'info',
                'icone' => $n->data['icon'] ?? 'fa-bell',
                'cor' => $n->data['color'] ?? 'blue',
                'titulo' => $n->data['title'] ?? __('Notificação'),
                'mensagem' => $n->data['message'] ?? '',
                'quando' => $n->created_at->diffForHumans(),
                'ligacao' => $n->data['url'] ?? null,
                'lida' => $n->read_at !== null,
                'da_base' => true,
            ]);

        $sistema = $this->sistema->para($user);
        $vistas = session('notif_seen_signature');
        $sistemaLido = $vistas !== null && $vistas === NotificacoesDoSistema::assinatura($sistema);

        $doSistema = ($soPorLer && $sistemaLido) ? [] : array_map(fn ($n) => $n + ['id' => null, 'lida' => $sistemaLido, 'da_base' => false], $sistema);

        return response()->json([
            'notificacoes' => $daBase->concat($doSistema)->values(),
            'por_ler' => $user->unreadNotifications()->count() + ($sistemaLido ? 0 : count($sistema)),
        ]);
    }

    public function marcarComoLida(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->update(['read_at' => now()]);

        return response()->json(['message' => __('Notificação marcada como lida.')]);
    }

    public function marcarTodasComoLidas(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        // As calculadas não têm linha: fica a «assinatura» do que se viu.
        session(['notif_seen_signature' => NotificacoesDoSistema::assinatura($this->sistema->para($user))]);

        return response()->json(['message' => __('Notificações marcadas como lidas.')]);
    }

    public function apagar(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return response()->json(['message' => __('Notificação excluída.')]);
    }

    public function limparTodas(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->notifications()->delete();
        session(['notif_seen_signature' => NotificacoesDoSistema::assinatura($this->sistema->para($user))]);

        return response()->json(['message' => __('Notificações limpas.')]);
    }

    public function mensagens(Request $request): JsonResponse
    {
        return response()->json(['mensagens' => $this->mensagens->porMostrar($request->user())]);
    }

    public function dispensar(Request $request, int $id): JsonResponse
    {
        try {
            $this->mensagens->dispensar($request->user(), $id);
        } catch (\Throwable $e) {
            \Log::warning('Não foi possível registar a dispensa da mensagem', ['mensagem' => $id, 'erro' => $e->getMessage()]);
        }

        return response()->json(['message' => __('Mensagem dispensada.')]);
    }

    public function avisos(Request $request): JsonResponse
    {
        return response()->json(['avisos' => $this->mensagens->paraOPainel($request->user())]);
    }

    /** A página inicial (`/home`) de quem trabalha numa empresa. */
    public function inicio(Request $request, PaginaInicial $pagina): JsonResponse
    {
        return response()->json($pagina->para($request->user()));
    }
}
