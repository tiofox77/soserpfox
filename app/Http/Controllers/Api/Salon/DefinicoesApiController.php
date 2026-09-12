<?php

namespace App\Http\Controllers\Api\Salon;

use App\Http\Controllers\Controller;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * AS DEFINIÇÕES DO SALÃO — a casa, a agenda e a página de marcação.
 *
 * DUAS GRAVAÇÕES SEPARADAS e não uma só: as REGRAS (horário, intervalos,
 * antecedência, depósito) e a PÁGINA PÚBLICA (nome, cores, textos, serviços em
 * destaque). Pôr o endereço público ao alcance de um clique numa caixa de
 * horário é o caminho para publicar a marcação online sem querer.
 *
 * O INTERVALO DA AGENDA (`slot_interval`) é a peça mais consequente daqui: é
 * ele que decide as horas que a página pública oferece. A 15 minutos, um corte
 * de 30 pode começar às 9h15 e empurrar o dia todo; a 60, perdem-se meias
 * horas boas.
 */
class DefinicoesApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.view');

        $d = SalonSettings::getForTenant();

        return response()->json([
            'regras' => [
                'opening_time' => $d->opening_time?->format('H:i') ?: '09:00',
                'closing_time' => $d->closing_time?->format('H:i') ?: '19:00',
                'working_days' => array_map('intval', $d->working_days ?? [1, 2, 3, 4, 5, 6]),
                'slot_interval' => (int) ($d->slot_interval ?? 30),
                'min_advance_booking_hours' => (int) ($d->min_advance_booking_hours ?? 2),
                'max_advance_booking_days' => (int) ($d->max_advance_booking_days ?? 30),
                'cancellation_hours' => (int) ($d->cancellation_hours ?? 24),
                'reminder_hours' => (int) ($d->reminder_hours ?? 24),
                'online_booking_enabled' => (bool) ($d->online_booking_enabled ?? true),
                'require_confirmation' => (bool) ($d->require_confirmation ?? true),
                'no_show_fee_percent' => (float) ($d->no_show_fee_percent ?? 0),
                'require_deposit' => (bool) $d->require_deposit,
                'deposit_percent' => (float) ($d->deposit_percent ?? 0),
                'allow_online_payment' => (bool) $d->allow_online_payment,
            ],
            'pagina' => [
                'salon_name' => (string) ($d->salon_name ?? ''),
                'salon_description' => (string) ($d->salon_description ?? ''),
                'salon_address' => (string) ($d->salon_address ?? ''),
                'salon_phone' => (string) ($d->salon_phone ?? ''),
                'salon_whatsapp' => (string) ($d->salon_whatsapp ?? ''),
                'salon_email' => (string) ($d->salon_email ?? ''),
                'salon_instagram' => (string) ($d->salon_instagram ?? ''),
                'salon_facebook' => (string) ($d->salon_facebook ?? ''),
                'salon_tiktok' => (string) ($d->salon_tiktok ?? ''),
                'salon_website' => (string) ($d->salon_website ?? ''),
                'salon_google_maps_url' => (string) ($d->salon_google_maps_url ?? ''),
                'primary_color' => (string) ($d->primary_color ?: '#ec4899'),
                'secondary_color' => (string) ($d->secondary_color ?: '#8b5cf6'),
                'meta_title' => (string) ($d->meta_title ?? ''),
                'meta_description' => (string) ($d->meta_description ?? ''),
                'welcome_message' => (string) ($d->welcome_message ?? ''),
                'confirmation_message' => (string) ($d->confirmation_message ?? ''),
                'booking_terms' => (string) ($d->booking_terms ?? ''),
                'cancellation_policy' => (string) ($d->cancellation_policy ?? ''),
                'featured_services' => array_map('intval', $d->featured_services ?? []),
            ],
            'logo' => $d->logo_url,
            'capa' => $d->cover_url,
            'endereco' => $d->booking_slug,
            'url_de_marcacao' => $d->booking_url,
            'dias' => collect(ProfissionaisApiController::DIAS)
                ->map(fn ($r, $v) => ['valor' => (int) $v, 'rotulo' => __($r)])->values(),
            'servicos' => Service::forTenant()->active()->orderBy('name')->get()
                ->map(fn (Service $s) => [
                    'valor' => (string) $s->id, 'rotulo' => $s->name,
                    'preco' => (float) $s->price, 'duracao' => (int) $s->duration,
                ])->values(),
            'permissoes' => ['pode_editar' => (bool) $request->user()?->can('salon.settings.edit')],
        ]);
    }

    /** As regras da casa e da agenda. */
    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.edit');

        $dados = $request->validate([
            'opening_time' => ['required', 'string', 'max:8'],
            'closing_time' => ['required', 'string', 'max:8', 'after:opening_time'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:0,6'],
            'slot_interval' => ['required', 'integer', 'min:5', 'max:120'],
            'min_advance_booking_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'max_advance_booking_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'reminder_hours' => ['nullable', 'integer', 'min:0', 'max:720'],
            'online_booking_enabled' => ['boolean'],
            'require_confirmation' => ['boolean'],
            'no_show_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'require_deposit' => ['boolean'],
            'deposit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allow_online_payment' => ['boolean'],
        ], [
            'closing_time.after' => __('A hora de fecho tem de ser depois da de abertura.'),
        ], [
            'opening_time' => __('abertura'), 'closing_time' => __('fecho'),
            'working_days' => __('dias de trabalho'), 'slot_interval' => __('intervalo'),
        ]);

        // UM DEPÓSITO EXIGIDO A ZERO POR CENTO não é um depósito: é um passo a
        // mais na marcação que não cobra nada.
        if ($request->boolean('require_deposit') && (float) ($dados['deposit_percent'] ?? 0) <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deposit_percent' => [__('Um depósito exigido tem de ter uma percentagem.')],
            ]);
        }

        $d = SalonSettings::getForTenant();

        $d->fill([
            'opening_time' => $dados['opening_time'],
            'closing_time' => $dados['closing_time'],
            'working_days' => array_values(array_map('intval', $dados['working_days'])),
            'slot_interval' => $dados['slot_interval'],
            'min_advance_booking_hours' => $dados['min_advance_booking_hours'],
            'max_advance_booking_days' => $dados['max_advance_booking_days'],
            'cancellation_hours' => $dados['cancellation_hours'] ?? 24,
            'reminder_hours' => $dados['reminder_hours'] ?? 24,
            'online_booking_enabled' => $request->boolean('online_booking_enabled'),
            'require_confirmation' => $request->boolean('require_confirmation'),
            'no_show_fee_percent' => (float) ($dados['no_show_fee_percent'] ?? 0),
            'require_deposit' => $request->boolean('require_deposit'),
            'deposit_percent' => (float) ($dados['deposit_percent'] ?? 0),
            'allow_online_payment' => $request->boolean('allow_online_payment'),
        ])->save();

        return response()->json(['message' => __('Configurações guardadas.')]);
    }

    /** A página de marcação: o que a cliente vê. */
    public function guardarPagina(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.edit');

        $tenantId = activeTenantId();

        $dados = $request->validate([
            'salon_name' => ['required', 'string', 'min:2', 'max:255'],
            'salon_description' => ['nullable', 'string', 'max:2000'],
            'salon_address' => ['nullable', 'string', 'max:255'],
            'salon_phone' => ['nullable', 'string', 'max:50'],
            'salon_whatsapp' => ['nullable', 'string', 'max:50'],
            'salon_email' => ['nullable', 'email', 'max:255'],
            'salon_instagram' => ['nullable', 'string', 'max:255'],
            'salon_facebook' => ['nullable', 'string', 'max:255'],
            'salon_tiktok' => ['nullable', 'string', 'max:255'],
            'salon_website' => ['nullable', 'string', 'max:255'],
            'salon_google_maps_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'welcome_message' => ['nullable', 'string', 'max:1000'],
            'confirmation_message' => ['nullable', 'string', 'max:1000'],
            'booking_terms' => ['nullable', 'string', 'max:5000'],
            'cancellation_policy' => ['nullable', 'string', 'max:5000'],
            'featured_services' => ['nullable', 'array'],
            'featured_services.*' => ['integer'],
        ], [
            'primary_color.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
            'secondary_color.regex' => __('A cor tem de ser um código do tipo #RRGGBB.'),
        ], ['salon_name' => __('nome do salão')]);

        $d = SalonSettings::getForTenant();

        // OS DESTAQUES TÊM DE SER DESTA EMPRESA: o `featured_services` é uma
        // lista de ids vinda do browser, e a página pública lê-a tal e qual.
        $destaques = Service::forTenant()
            ->whereIn('id', $dados['featured_services'] ?? [])
            ->pluck('id')->map(fn ($i) => (int) $i)->values()->all();

        $d->fill(collect($dados)->except('featured_services')->all() + [
            'featured_services' => $destaques,
        ]);

        // O ENDEREÇO NASCE COM O NOME, para quem só quer ligar o interruptor.
        if (empty($d->booking_slug)) {
            $d->booking_slug = SalonSettings::generateUniqueSlug($dados['salon_name']);
        }

        $d->save();

        return response()->json([
            'message' => __('Página de marcação guardada.'),
            'endereco' => $d->booking_slug,
            'url_de_marcacao' => $d->booking_url,
        ]);
    }

    /** O logótipo e a capa. */
    public function imagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.edit');

        $dados = $request->validate([
            'qual' => ['required', Rule::in(['logo', 'capa'])],
            'ficheiro' => ['required', 'image', 'max:5120'],
        ], ['ficheiro.max' => __('A imagem não pode passar dos 5 MB.')]);

        $tenantId = activeTenantId();
        $d = SalonSettings::getForTenant();

        $coluna = $dados['qual'] === 'logo' ? 'logo' : 'cover_image';

        // A imagem nova substitui a anterior E APAGA-A: uma capa trocada dez
        // vezes não pode deixar dez ficheiros no disco.
        if ($d->{$coluna} && Storage::disk('public')->exists($d->{$coluna})) {
            Storage::disk('public')->delete($d->{$coluna});
        }

        $pasta = 'salon/'.($dados['qual'] === 'logo' ? 'logos' : 'covers').'/'.$tenantId;

        $d->{$coluna} = $request->file('ficheiro')->store($pasta, 'public');
        $d->save();

        return response()->json([
            'message' => __('Imagem guardada.'),
            'url' => $dados['qual'] === 'logo' ? $d->logo_url : $d->cover_url,
        ]);
    }

    public function removerImagem(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.edit');

        $dados = $request->validate(['qual' => ['required', Rule::in(['logo', 'capa'])]]);

        $d = SalonSettings::getForTenant();
        $coluna = $dados['qual'] === 'logo' ? 'logo' : 'cover_image';

        if ($d->{$coluna} && Storage::disk('public')->exists($d->{$coluna})) {
            Storage::disk('public')->delete($d->{$coluna});
        }

        $d->{$coluna} = null;
        $d->save();

        return response()->json(['message' => __('Imagem removida.')]);
    }

    /**
     * UM ENDEREÇO NOVO PARTE O ANTIGO.
     *
     * O link antigo deixa de funcionar — e ele pode estar num cartaz, numa
     * biografia do Instagram ou num QR já impresso. Por isso é um botão
     * próprio, e não um campo que se edita por engano.
     */
    public function novoEndereco(Request $request): JsonResponse
    {
        $this->exigir($request, 'salon.settings.edit');

        $d = SalonSettings::getForTenant();
        $d->regenerateSlug();

        return response()->json([
            'message' => __('Endereço de marcação novo. O anterior deixou de funcionar.'),
            'endereco' => $d->booking_slug,
            'url_de_marcacao' => $d->booking_url,
        ]);
    }
}
