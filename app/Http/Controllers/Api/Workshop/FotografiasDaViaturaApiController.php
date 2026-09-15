<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\Vehicle;
use App\Models\Workshop\VehiclePhoto;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderAttachment;
use App\Models\Workshop\WorkOrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * AS FOTOGRAFIAS DA VIATURA — antes, durante, depois e danos (15/09/2026).
 *
 * Pedido: «uma área para juntar imagens da viatura, antes e depois,
 * principalmente para serviços de bate-chapa, pintura e outros». Vêem-se com a
 * permissão de ver viaturas e juntam-se, mudam-se e tiram-se com a de editar.
 *
 * A LISTA TRAZ TAMBÉM AS FOTOGRAFIAS DAS FOLHAS DE OBRA desta viatura (os anexos
 * «Foto antes/depois/dano» que já existiam): só se lêem aqui — tiram-se na ordem
 * onde foram postas, que é onde ficou o registo no histórico.
 */
class FotografiasDaViaturaApiController extends Controller
{
    private const DOS_ANEXOS = ['photo_before' => 'antes', 'photo_after' => 'depois', 'photo_damage' => 'dano'];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function viatura(int $id): Vehicle
    {
        return Vehicle::where('tenant_id', activeTenantId())->findOrFail($id);
    }

    public function index(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.view');
        $viatura = $this->viatura($id);

        $ordens = WorkOrder::where('tenant_id', $viatura->tenant_id)->where('vehicle_id', $viatura->id)
            ->orderByDesc('received_at')->orderByDesc('id')
            ->get(['id', 'order_number', 'received_at', 'status']);
        $numeros = $ordens->pluck('order_number', 'id');

        $proprias = VehiclePhoto::with('user:id,name')
            ->where('tenant_id', $viatura->tenant_id)->where('vehicle_id', $viatura->id)
            ->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (VehiclePhoto $f) => self::paraEcra($f, $numeros->all()));

        $dasOrdens = WorkOrderAttachment::with('user:id,name')
            ->whereIn('work_order_id', $ordens->pluck('id'))
            ->whereIn('category', array_keys(self::DOS_ANEXOS))
            ->orderByDesc('created_at')->get()
            ->filter(fn (WorkOrderAttachment $a) => $a->is_image)
            ->map(fn (WorkOrderAttachment $a) => [
                'id' => 'anexo-' . $a->id,
                'origem' => 'ordem',
                'url' => $a->file_url,
                'fase' => self::DOS_ANEXOS[$a->category],
                'servico' => 'outros',
                'zona' => null,
                'descricao' => $a->description,
                'ordem_id' => $a->work_order_id,
                'ordem' => $numeros[$a->work_order_id] ?? null,
                'nome' => $a->original_filename,
                'largura' => null,
                'altura' => null,
                'por' => $a->user?->name,
                'em' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'fotos' => $proprias->concat($dasOrdens)->sortByDesc('em')->values(),
            'ordens' => $ordens->map(fn (WorkOrder $o) => [
                'valor' => (string) $o->id,
                'rotulo' => $o->order_number . ($o->received_at ? ' · ' . $o->received_at->format('d/m/Y') : ''),
            ])->values(),
            'listas' => VehiclePhoto::listas(),
            'pode_editar' => (bool) $request->user()?->can('workshop.vehicles.edit'),
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $viatura = $this->viatura($id);

        $dados = $request->validate([
            'fotografias' => ['required', 'array', 'max:12'],
            'fotografias.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
            ...$this->regras(),
        ], [
            'fotografias.required' => __('Escolha pelo menos uma fotografia.'),
            'fotografias.max' => __('No máximo 12 fotografias de cada vez.'),
            'fotografias.*.image' => __('Só se aceitam imagens (JPG, PNG, WebP ou GIF).'),
            'fotografias.*.mimes' => __('Só se aceitam imagens (JPG, PNG, WebP ou GIF).'),
            'fotografias.*.max' => __('Cada fotografia pode ter no máximo 8 MB.'),
        ]);

        $ordem = $this->ordemDaViatura($viatura, $dados['ordem_id'] ?? null);
        $criadas = [];

        foreach ($request->file('fotografias') as $ficheiro) {
            /** @var UploadedFile $ficheiro */
            $medidas = @getimagesize($ficheiro->getRealPath()) ?: [null, null];
            $caminho = $ficheiro->storeAs(
                "workshop/vehicles/{$viatura->id}",
                uniqid(($dados['fase'] ?? 'foto') . '_') . '.' . $ficheiro->extension(),
                'public'
            );

            $criadas[] = VehiclePhoto::create([
                'tenant_id' => $viatura->tenant_id,
                'vehicle_id' => $viatura->id,
                'work_order_id' => $ordem?->id,
                'user_id' => auth()->id(),
                'phase' => $dados['fase'],
                'service' => $dados['servico'],
                'zone' => $dados['zona'] ?? null,
                'description' => $dados['descricao'] ?? null,
                'file_path' => $caminho,
                'original_filename' => mb_substr($ficheiro->getClientOriginalName(), 0, 255),
                'mime_type' => $ficheiro->getMimeType(),
                'file_size' => (int) $ficheiro->getSize(),
                'width' => $medidas[0] ? min(65535, (int) $medidas[0]) : null,
                'height' => $medidas[1] ? min(65535, (int) $medidas[1]) : null,
            ]);
        }

        if ($ordem) {
            WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                __(':n fotografia(s) da viatura (:fase · :servico).', [
                    'n' => count($criadas),
                    'fase' => __(VehiclePhoto::FASES[$dados['fase']]),
                    'servico' => __(VehiclePhoto::SERVICOS[$dados['servico']]),
                ]));
        }

        $numeros = $ordem ? [$ordem->id => $ordem->order_number] : [];

        return response()->json([
            'data' => array_map(fn (VehiclePhoto $f) => self::paraEcra($f, $numeros), $criadas),
            'message' => trans_choice(':n fotografia juntada.|:n fotografias juntadas.', count($criadas), ['n' => count($criadas)]),
        ], 201);
    }

    /** Mudar a fase, o serviço, a zona, a folha de obra ou a descrição. */
    public function update(Request $request, int $id, int $foto): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $viatura = $this->viatura($id);
        $f = VehiclePhoto::where('tenant_id', $viatura->tenant_id)->where('vehicle_id', $viatura->id)->findOrFail($foto);

        $dados = $request->validate($this->regras());
        $ordem = $this->ordemDaViatura($viatura, $dados['ordem_id'] ?? null);

        $f->update([
            'phase' => $dados['fase'],
            'service' => $dados['servico'],
            'zone' => $dados['zona'] ?? null,
            'description' => $dados['descricao'] ?? null,
            'work_order_id' => $ordem?->id,
        ]);

        return response()->json([
            'data' => self::paraEcra($f->fresh('user'), $ordem ? [$ordem->id => $ordem->order_number] : []),
            'message' => __('Fotografia actualizada.'),
        ]);
    }

    public function destroy(Request $request, int $id, int $foto): JsonResponse
    {
        $this->exigir($request, 'workshop.vehicles.edit');
        $viatura = $this->viatura($id);

        VehiclePhoto::where('tenant_id', $viatura->tenant_id)->where('vehicle_id', $viatura->id)->findOrFail($foto)->delete();

        return response()->json(['message' => __('Fotografia removida.')]);
    }

    /* ─── Ferramentas ─────────────────────────────────────────────────── */

    private function regras(): array
    {
        return [
            'fase' => ['required', Rule::in(array_keys(VehiclePhoto::FASES))],
            'servico' => ['required', Rule::in(array_keys(VehiclePhoto::SERVICOS))],
            'zona' => ['nullable', Rule::in(array_keys(VehiclePhoto::ZONAS))],
            'ordem_id' => ['nullable', 'integer'],
            'descricao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** A folha de obra tem de ser DESTA viatura — o id vem do browser. */
    private function ordemDaViatura(Vehicle $viatura, $ordemId): ?WorkOrder
    {
        if (! $ordemId) {
            return null;
        }

        $ordem = WorkOrder::where('tenant_id', $viatura->tenant_id)->where('vehicle_id', $viatura->id)->find((int) $ordemId);

        if (! $ordem) {
            throw ValidationException::withMessages(['ordem_id' => [__('Essa folha de obra não é desta viatura.')]]);
        }

        return $ordem;
    }

    public static function paraEcra(VehiclePhoto $f, array $numeros = []): array
    {
        return [
            'id' => $f->id,
            'origem' => 'viatura',
            'url' => $f->url,
            'fase' => $f->phase,
            'servico' => $f->service,
            'zona' => $f->zone,
            'descricao' => $f->description,
            'ordem_id' => $f->work_order_id,
            'ordem' => $f->work_order_id ? ($numeros[$f->work_order_id] ?? $f->workOrder?->order_number) : null,
            'nome' => $f->original_filename,
            'largura' => $f->width,
            'altura' => $f->height,
            'por' => $f->user?->name,
            'em' => $f->created_at?->toIso8601String(),
        ];
    }
}
