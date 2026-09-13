<?php

namespace App\Http\Controllers\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\Client;
use App\Models\Salon\SalonSettings;
use App\Services\Salon\AgendamentoOnline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A PÁGINA PÚBLICA DO SALÃO — a montra, os horários, a conta e a marcação.
 *
 * Fora de qualquer `auth`: quem abre isto é uma cliente a partir de uma ligação
 * do Instagram. QUEM ENTROU fica guardado na SESSÃO, e não num id que o browser
 * manda: marcar em nome de outra ficha passa a exigir ter entrado nela.
 */
class AgendamentoOnlineController extends Controller
{
    private function definicoes(string $slug): SalonSettings
    {
        $d = SalonSettings::getBySlug($slug);

        // Uma empresa desactivada, ou sem o módulo, não tem salão público.
        abort_unless($d && \App\Support\CasaPublica::aberta((int) $d->tenant_id, 'salon'), 404, __('Salão não encontrado'));
        abort_unless($d->online_booking_enabled, 403, __('Agendamento online não está disponível'));

        return $d;
    }

    private function chave(SalonSettings $d): string
    {
        return 'salao-publico.'.$d->tenant_id.'.cliente';
    }

    private function quemEntrou(SalonSettings $d): ?Client
    {
        $id = session($this->chave($d));

        return $id ? Client::where('tenant_id', $d->tenant_id)->find($id) : null;
    }

    public function pagina(string $slug)
    {
        $d = $this->definicoes($slug);
        $servico = new AgendamentoOnline($d);
        $dados = $servico->paraAPagina();
        $cliente = $this->quemEntrou($d);

        return view('react.publico', [
            'ecra' => 'salao/agendar',
            'props' => ['slug' => $slug, 'cliente' => $cliente ? $servico->paraACliente($cliente) : null] + $dados,
            'titulo' => $d->salon_name ? $d->salon_name . ' — ' . __('Marcação online') : __('Agendar'),
            'canonico' => \App\Support\DadosEstruturados::raiz() . '/agendar/' . $slug,
            'robots' => \App\Support\CasaPublica::temConteudo($d->salon_description, $d->welcome_message) ? null : 'noindex, follow',
            'dadosEstruturados' => \App\Support\CasaPublica::dadosEstruturados('BeautySalon', \App\Support\DadosEstruturados::raiz() . '/agendar/' . $slug, (int) $d->tenant_id, [
                'nome' => (string) ($d->salon_name ?: \App\Models\Tenant::find($d->tenant_id)?->name),
                'descricao' => $d->salon_description,
                'imagem' => $d->cover_url ?: $d->logo_url,
                'telefone' => $d->salon_phone,
                'email' => $d->salon_email,
                'morada' => $d->salon_address,
                'redes' => [$d->salon_website, $d->salon_instagram, $d->salon_facebook, $d->salon_tiktok],
                'mapa' => $d->salon_google_maps_url,
            ]),
            'descricao' => $d->salon_description ?: ($d->welcome_message ?: __('Agende online')),
            'imagem' => $d->cover_url ?: $d->logo_url,
        ]);
    }

    public function horarios(Request $request, string $slug): JsonResponse
    {
        $dados = $request->validate([
            'profissional' => 'required|integer',
            'data' => 'required|date_format:Y-m-d',
            'servicos' => 'required|array|min:1|max:20',
            'servicos.*' => 'integer',
        ]);

        return response()->json([
            'horarios' => (new AgendamentoOnline($this->definicoes($slug)))->horarios((int) $dados['profissional'], $dados['data'], $dados['servicos']),
        ]);
    }

    public function entrar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);
        $dados = $request->validate([
            'telefone' => 'required|string|min:6|max:50',
            'password' => 'required|string|max:100',
        ], [
            'telefone.required' => __('Informe o número de telefone'),
            'password.required' => __('Informe a password'),
        ]);

        // Adivinhar a senha de uma ficha pelo telefone não pode ser à vontade.
        $travao = 'salao-entrar:'.$d->tenant_id.':'.AgendamentoOnline::numero($dados['telefone']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($travao, 5)) {
            throw ValidationException::withMessages(['telefone' => __('Demasiadas tentativas. Tente de novo dentro de :s segundos.', ['s' => RateLimiter::availableIn($travao)])]);
        }
        RateLimiter::hit($travao, 60);

        $servico = new AgendamentoOnline($d);
        $cliente = $servico->entrar($dados['telefone'], $dados['password']);
        RateLimiter::clear($travao);

        $request->session()->regenerate();
        session([$this->chave($d) => $cliente->id]);

        return response()->json(['cliente' => $servico->paraACliente($cliente), 'message' => __('Bem-vindo, :nome!', ['nome' => $cliente->first_name])]);
    }

    public function registar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);
        $dados = $request->validate([
            'nome' => 'required|string|min:2|max:255',
            'telefone' => 'required|string|min:6|max:50',
            'email' => 'nullable|email|max:255',
            'password' => 'required|string|min:4|max:100|confirmed',
        ], [
            'nome.required' => __('Informe o seu nome'),
            'telefone.required' => __('Informe o telefone'),
            'password.min' => __('Password deve ter pelo menos 4 caracteres'),
            'password.confirmed' => __('As passwords não coincidem'),
        ]);

        $servico = new AgendamentoOnline($d);
        $cliente = $servico->registar($dados['nome'], $dados['telefone'], $dados['email'] ?? null, $dados['password']);

        $request->session()->regenerate();
        session([$this->chave($d) => $cliente->id]);

        return response()->json(['cliente' => $servico->paraACliente($cliente), 'message' => __('Conta criada com sucesso!')], 201);
    }

    public function sair(string $slug): JsonResponse
    {
        session()->forget($this->chave($this->definicoes($slug)));

        return response()->json(['ok' => true]);
    }

    public function marcar(Request $request, string $slug): JsonResponse
    {
        $d = $this->definicoes($slug);
        $cliente = $this->quemEntrou($d);

        $dados = $request->validate([
            'servicos' => 'required|array|min:1|max:20',
            'servicos.*' => 'integer',
            'profissional' => 'required|integer',
            'data' => 'required|date_format:Y-m-d',
            'hora' => 'required|date_format:H:i',
            'notas' => 'nullable|string|max:1000',
            'nome' => [$cliente ? 'nullable' : 'required', 'string', 'min:2', 'max:255'],
            'telefone' => [$cliente ? 'nullable' : 'required', 'string', 'min:9', 'max:50'],
            'email' => 'nullable|email|max:255',
        ], [
            'servicos.required' => __('Selecione pelo menos um serviço'),
            'profissional.required' => __('Selecione um profissional'),
            'hora.required' => __('Selecione data e horário'),
        ]);

        $m = (new AgendamentoOnline($d))->marcar(
            $dados,
            $cliente,
            $cliente ? ['email' => $dados['email'] ?? null] : ['nome' => $dados['nome'], 'telefone' => $dados['telefone'], 'email' => $dados['email'] ?? null],
        );

        return response()->json([
            'numero' => $m->appointment_number,
            'estado' => $m->status,
            'total' => (float) $m->total,
        ], 201);
    }
}
