<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SossaudeController extends Controller
{
    public function connection(Request $request)
    {
        $c = $request->attributes->get('sossaude_credential');
        return response()->json([
            'contract_version' => '1', 'tenant_id' => (string) $c->tenant_id,
            'capabilities' => array_values(array_intersect(
                json_decode($c->scopes, true) ?: [], ['billing-drafts.write']
            )),
        ]);
    }

    public function store(Request $request)
    {
        $c = $request->attributes->get('sossaude_credential');
        if (!in_array('billing-drafts.write', json_decode($c->scopes, true) ?: [], true)) {
            return response()->json(['error' => 'insufficient_scope'], 403);
        }
        Validator::make(['key' => $request->header('Idempotency-Key')], ['key' => 'required|uuid'])->validate();
        $body = $request->json()->all();
        $allowed = ['contract_version', 'source', 'source_reference', 'currency', 'payer_type', 'payer', 'lines', 'discount_amount', 'due_date'];
        if (array_diff(array_keys($body), $allowed) || $request->query->count()) {
            throw ValidationException::withMessages(['body' => 'Campos não permitidos.']);
        }
        $data = Validator::make($body, [
            'contract_version' => 'required|string|in:1',
            'source' => 'required|string|in:sossaude',
            'source_reference' => 'required|uuid',
            'currency' => 'required|string|in:AOA',
            'payer_type' => 'required|in:particular,insurance,company',
            'payer' => 'required|array:name,nif,email,phone',
            'payer.name' => 'required|string|max:255',
            'payer.nif' => 'nullable|string|max:30',
            'payer.email' => 'nullable|email|max:255',
            'payer.phone' => 'nullable|string|max:40',
            'lines' => 'required|array|min:1|max:200',
            'lines.*' => 'required|array:source_line_id,product_id,quantity,unit_price',
            'lines.*.source_line_id' => 'required|string|max:80|distinct:strict',
            'lines.*.product_id' => 'required|string|regex:/^[1-9][0-9]{0,17}$/',
            'lines.*.quantity' => ['required', 'string', 'regex:/^[0-9]{1,9}(\.[0-9]{1,4})?$/', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'string', 'regex:/^[0-9]{1,12}(\.[0-9]{1,2})?$/'],
            'discount_amount' => ['required', 'string', 'regex:/^[0-9]{1,12}(\.[0-9]{1,2})?$/'],
            'due_date' => 'nullable|date_format:Y-m-d',
        ])->validate();
        $data['source_reference'] = strtolower($data['source_reference']);
        $hash = hash('sha256', json_encode($this->canonical($data), JSON_THROW_ON_ERROR));
        $key = strtolower($request->header('Idempotency-Key'));

        return DB::transaction(function () use ($c, $data, $hash, $key) {
            // Tenant lock serializes reference deduplication across credential rotations.
            $tenant = DB::table('tenants')->where('id', $c->tenant_id)->lockForUpdate()->first();
            $current = DB::table('sossaude_credentials')->where('id', $c->id)->lockForUpdate()->first();
            if (!$current || $current->revoked_at || now()->gte($current->expires_at)) {
                return response()->json(['error' => 'invalid_credentials'], 401);
            }
            if (!in_array('billing-drafts.write', json_decode($current->scopes, true) ?: [], true)) {
                return response()->json(['error' => 'insufficient_scope'], 403);
            }
            if (!$tenant || !$tenant->is_active || $tenant->deleted_at) {
                return response()->json(['error' => 'tenant_unavailable'], 403);
            }
            $previous = DB::table('sossaude_requests')->where([
                'tenant_id' => $c->tenant_id, 'credential_id' => $c->id,
                'operation' => 'billing-drafts.create', 'idempotency_key' => $key,
            ])->first();
            if ($previous) {
                return $previous->body_hash !== $hash
                    ? response()->json(['error' => 'idempotency_conflict'], 409)
                    : response()->json(json_decode($previous->response, true), $previous->http_status)
                        ->header('Idempotent-Replay', 'true');
            }
            $draft = DB::table('sossaude_billing_drafts')->where([
                'tenant_id' => $c->tenant_id, 'source' => 'sossaude',
                'source_reference' => $data['source_reference'],
            ])->first();
            if ($draft && $draft->body_hash !== $hash) {
                return response()->json(['error' => 'source_reference_conflict'], 409);
            }
            $status = $draft ? 200 : 201;
            if (!$draft) {
                $ids = array_unique(array_column($data['lines'], 'product_id'));
                $found = DB::table('invoicing_products')->where('tenant_id', $c->tenant_id)
                    ->whereIn('id', $ids)->whereNull('deleted_at')->where('is_active', true)
                    ->lockForUpdate()->pluck('id')->map(fn ($id) => (string) $id)->all();
                if (array_diff($ids, $found)) {
                    throw ValidationException::withMessages(['lines' => 'Artigo indisponível para esta empresa.']);
                }
                $id = DB::table('sossaude_billing_drafts')->insertGetId([
                    'tenant_id' => $c->tenant_id, 'credential_id' => $c->id,
                    'source' => 'sossaude', 'source_reference' => $data['source_reference'],
                    'body_hash' => $hash, 'encrypted_payload' => Crypt::encryptString(json_encode($data, JSON_THROW_ON_ERROR)),
                    'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $id = $draft->id;
            }
            $response = ['contract_version' => '1', 'tenant_id' => (string) $c->tenant_id,
                'draft_id' => (string) $id, 'status' => 'draft', 'source_reference' => $data['source_reference']];
            DB::table('sossaude_requests')->insert([
                'tenant_id' => $c->tenant_id, 'credential_id' => $c->id,
                'operation' => 'billing-drafts.create', 'idempotency_key' => $key,
                'body_hash' => $hash, 'draft_id' => $id, 'http_status' => $status,
                'response' => json_encode($response, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now(),
            ]);
            return response()->json($response, $status);
        }, 3);
    }

    private function canonical(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as &$value) {
            if (is_array($value)) {
                $value = $this->canonical($value);
            }
        }
        return $data;
    }
}
