<?php

namespace App\Http\Controllers\Api\Workshop;

use App\Http\Controllers\Controller;
use App\Models\Workshop\CourtesyCar;
use App\Models\Workshop\CourtesyLoan;
use App\Models\Workshop\WorkOrder;
use App\Models\Workshop\WorkOrderHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * AS VIATURAS DE CORTESIA (15/09/2026, OF-17).
 *
 * O quadro das viaturas (disponível, emprestada, atrasada, em manutenção), o
 * empréstimo e a devolução. Ver pede ver ordens; emprestar e receber pedem
 * editar ordens. As viaturas gerem-se no catálogo `viaturas-de-cortesia`.
 */
class CortesiaApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.view');
        $tenantId = activeTenantId();

        $viaturas = CourtesyCar::where('tenant_id', $tenantId)->with(['openLoan.workOrder:id,order_number'])->orderBy('plate')->get();
        $recentes = CourtesyLoan::where('tenant_id', $tenantId)->with(['car' => fn ($q) => $q->withTrashed(), 'workOrder:id,order_number', 'user:id,name'])
            ->orderByDesc('out_at')->limit(15)->get();

        $cartoes = $viaturas->map(fn (CourtesyCar $c) => self::viaturaParaEcra($c));

        return response()->json([
            'data' => $cartoes->values(),
            'recentes' => $recentes->map(fn (CourtesyLoan $l) => self::emprestimoParaEcra($l))->values(),
            'contas' => [
                'total' => $viaturas->where('status', '!=', 'inactiva')->count(),
                'disponiveis' => $cartoes->where('estado', 'disponivel')->count(),
                'emprestadas' => $cartoes->where('estado', 'emprestada')->count(),
                'atrasadas' => $cartoes->filter(fn ($c) => $c['emprestimo']['atrasado'] ?? false)->count(),
            ],
            // As ordens abertas, para ligar o empréstimo à do cliente.
            'ordens' => WorkOrder::where('tenant_id', $tenantId)->whereNotIn('status', ['delivered', 'cancelled'])
                ->with('vehicle:id,plate,owner_name,owner_phone')->orderByDesc('received_at')->limit(200)->get()
                ->map(fn (WorkOrder $o) => [
                    'valor' => (string) $o->id,
                    'rotulo' => trim("{$o->order_number} · " . ($o->vehicle?->plate ?? '') . ' · ' . ($o->vehicle?->owner_name ?? '')),
                    'dono' => $o->vehicle?->owner_name,
                    'telefone' => $o->vehicle?->owner_phone,
                ])->values(),
            'pode_gerir' => (bool) $request->user()?->can('workshop.work-orders.edit'),
        ]);
    }

    public function emprestar(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $tenantId = activeTenantId();
        $dados = $request->validate([
            'ordem_id' => ['nullable', 'integer'],
            'condutor' => ['required', 'string', 'max:150'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'carta' => ['nullable', 'string', 'max:40'],
            'km_saida' => ['required', 'integer', 'min:0', 'max:9999999'],
            'combustivel' => ['nullable', 'integer', 'min:0', 'max:8'],
            'devolver_ate' => ['nullable', 'date', 'after:now'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ], ['condutor.required' => __('Escreva o nome de quem leva a viatura.')]);

        $ordem = ! empty($dados['ordem_id']) ? WorkOrder::where('tenant_id', $tenantId)->find($dados['ordem_id']) : null;
        if (! empty($dados['ordem_id']) && ! $ordem) {
            throw ValidationException::withMessages(['ordem_id' => [__('Essa ordem não é desta oficina.')]]);
        }

        $emprestimo = DB::transaction(function () use ($tenantId, $id, $dados, $ordem, $request) {
            $viatura = CourtesyCar::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($id);

            if ($viatura->status !== 'disponivel' || CourtesyLoan::where('courtesy_car_id', $viatura->id)->whereNull('returned_at')->exists()) {
                throw ValidationException::withMessages(['condutor' => [__('A viatura :m não está disponível.', ['m' => $viatura->plate])]]);
            }
            if ((int) $dados['km_saida'] < (int) $viatura->mileage) {
                throw ValidationException::withMessages(['km_saida' => [__('A viatura já tem :km km: os km à saída não podem ser menos.', ['km' => number_format((int) $viatura->mileage, 0, ',', '.')])]]);
            }

            $l = CourtesyLoan::create([
                'tenant_id' => $tenantId,
                'courtesy_car_id' => $viatura->id,
                'work_order_id' => $ordem?->id,
                'driver_name' => trim($dados['condutor']),
                'driver_phone' => trim((string) ($dados['telefone'] ?? '')) ?: null,
                'driver_licence' => trim((string) ($dados['carta'] ?? '')) ?: null,
                'out_at' => now(),
                'expected_return_at' => $dados['devolver_ate'] ?? null,
                'mileage_out' => (int) $dados['km_saida'],
                'fuel_out' => $dados['combustivel'] ?? null,
                'notes_out' => trim((string) ($dados['notas'] ?? '')) ?: null,
                'user_id' => $request->user()?->id,
            ]);
            $viatura->update(['mileage' => (int) $dados['km_saida'], 'fuel_level' => $dados['combustivel'] ?? $viatura->fuel_level]);

            if ($ordem) {
                WorkOrderHistory::logAction($ordem->id, WorkOrderHistory::ACTION_COMMENT,
                    __('Viatura de cortesia :m emprestada a :nome (:km km).', ['m' => $viatura->plate, 'nome' => $l->driver_name, 'km' => number_format($l->mileage_out, 0, ',', '.')]));
            }

            return $l;
        });

        return response()->json(['message' => __('Viatura :m emprestada a :nome.', ['m' => $emprestimo->car->plate, 'nome' => $emprestimo->driver_name])]);
    }

    public function devolver(Request $request, int $id): JsonResponse
    {
        $this->exigir($request, 'workshop.work-orders.edit');
        $tenantId = activeTenantId();
        $dados = $request->validate([
            'km_entrada' => ['required', 'integer', 'min:0', 'max:9999999'],
            'combustivel' => ['nullable', 'integer', 'min:0', 'max:8'],
            'danos' => ['nullable', 'string', 'max:2000'],
        ]);

        $l = DB::transaction(function () use ($tenantId, $id, $dados, $request) {
            $l = CourtesyLoan::where('tenant_id', $tenantId)->lockForUpdate()->findOrFail($id);
            abort_if($l->returned_at, 422, __('Este empréstimo já foi devolvido.'));

            if ((int) $dados['km_entrada'] < $l->mileage_out) {
                throw ValidationException::withMessages(['km_entrada' => [__('Saiu com :km km: os km à entrada não podem ser menos.', ['km' => number_format($l->mileage_out, 0, ',', '.')])]]);
            }

            $l->update([
                'returned_at' => now(),
                'mileage_in' => (int) $dados['km_entrada'],
                'fuel_in' => $dados['combustivel'] ?? null,
                'damages_in' => trim((string) ($dados['danos'] ?? '')) ?: null,
                'returned_by' => $request->user()?->id,
            ]);
            $l->car()->withTrashed()->first()?->update(['mileage' => (int) $dados['km_entrada'], 'fuel_level' => $dados['combustivel'] ?? null]);

            if ($l->work_order_id) {
                WorkOrderHistory::logAction($l->work_order_id, WorkOrderHistory::ACTION_COMMENT,
                    __('Viatura de cortesia :m devolvida (:km km percorridos).', ['m' => $l->car?->plate, 'km' => number_format($l->mileage_in - $l->mileage_out, 0, ',', '.')])
                    . ($l->damages_in ? ' ' . __('Danos: :danos', ['danos' => $l->damages_in]) : ''));
            }

            return $l;
        });

        $menos = $l->fuel_out !== null && $l->fuel_in !== null && $l->fuel_in < $l->fuel_out;

        return response()->json(['message' => __('Viatura :m recebida: :km km percorridos.', ['m' => $l->car?->plate, 'km' => number_format($l->mileage_in - $l->mileage_out, 0, ',', '.')])
            . ($menos ? ' ' . __('Voltou com menos combustível.') : '')]);
    }

    public static function viaturaParaEcra(CourtesyCar $c): array
    {
        $l = $c->openLoan;

        return [
            'id' => $c->id,
            'matricula' => $c->plate,
            'marca_modelo' => trim("{$c->brand} {$c->model}"),
            'cor' => $c->color,
            'ano' => $c->year,
            'km' => (int) $c->mileage,
            'combustivel' => $c->fuel_level,
            'seguro_ate' => $c->insurance_expiry?->toDateString(),
            'seguro_caducado' => $c->insurance_expiry && $c->insurance_expiry->isPast(),
            'estado' => $l ? 'emprestada' : $c->status,
            'estado_rotulo' => $l ? __('Emprestada') : __(CourtesyCar::ESTADOS[$c->status] ?? $c->status),
            'emprestimo' => $l ? self::emprestimoParaEcra($l) : null,
        ];
    }

    public static function emprestimoParaEcra(CourtesyLoan $l): array
    {
        return [
            'id' => $l->id,
            'viatura_id' => $l->courtesy_car_id,
            'matricula' => $l->car?->plate,
            'condutor' => $l->driver_name,
            'telefone' => $l->driver_phone,
            'carta' => $l->driver_licence,
            'ordem_id' => $l->work_order_id,
            'ordem' => $l->workOrder?->order_number,
            'saida' => $l->out_at?->toIso8601String(),
            'devolver_ate' => $l->expected_return_at?->toIso8601String(),
            'devolvida' => $l->returned_at?->toIso8601String(),
            'atrasado' => $l->atrasado(),
            'km_saida' => $l->mileage_out,
            'km_entrada' => $l->mileage_in,
            'combustivel_saida' => $l->fuel_out,
            'combustivel_entrada' => $l->fuel_in,
            'notas' => $l->notes_out,
            'danos' => $l->damages_in,
            'por' => $l->user?->name,
        ];
    }
}
