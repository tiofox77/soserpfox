<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

class SossaudeBridgeTest extends TenantTestCase
{
    private array $body;
    private array $headers;
    private int $credential;
    private const URL = '/api/integrations/sossaude/v1';

    protected function setUp(): void
    {
        parent::setUp();
        $product = $this->produtoComStock(10, 1000);
        $this->credential = DB::table('sossaude_credentials')->insertGetId([
            'tenant_id' => $this->tenant->id, 'name' => 'test', 'token_hash' => hash('sha256', 'test-only'),
            'scopes' => json_encode(['billing-drafts.write']), 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->headers = ['Authorization' => 'Bearer test-only', 'X-Bridge-Version' => '1',
            'X-ERP-Tenant' => (string) $this->tenant->id, 'Idempotency-Key' => (string) Str::uuid()];
        $this->body = ['contract_version' => '1', 'source' => 'sossaude', 'source_reference' => (string) Str::uuid(),
            'currency' => 'AOA', 'payer_type' => 'particular', 'payer' => ['name' => 'Cliente sintético'],
            'lines' => [['source_line_id' => '1', 'product_id' => (string) $product->id, 'quantity' => '1', 'unit_price' => '1000.00']],
            'discount_amount' => '0.00', 'due_date' => null];
        Auth::logout();
    }

    public function test_connection_has_fixed_tenant_and_no_session_requirement(): void
    {
        $this->getJson(self::URL.'/connection', $this->headers)->assertOk()
            ->assertJsonPath('tenant_id', (string) $this->tenant->id)
            ->assertJsonPath('capabilities.0', 'billing-drafts.write');
        $this->getJson(self::URL.'/connection', array_replace($this->headers, ['X-ERP-Tenant' => '0']))->assertForbidden();
        $this->getJson(self::URL.'/connection', array_replace($this->headers, ['Authorization' => 'Bearer invalid']))->assertUnauthorized();
    }

    public function test_draft_is_encrypted_idempotent_and_has_no_fiscal_effects(): void
    {
        $before = DB::table('invoicing_sales_invoices')->count();
        $first = $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertCreated()->json();
        $this->postJson(self::URL.'/billing-drafts', array_reverse($this->body, true), $this->headers)
            ->assertCreated()->assertHeader('Idempotent-Replay', 'true')->assertExactJson($first);
        $this->postJson(self::URL.'/billing-drafts', $this->body,
            array_replace($this->headers, ['Idempotency-Key' => (string) Str::uuid()]))->assertOk()->assertExactJson($first);
        $row = DB::table('sossaude_billing_drafts')->where('id', $first['draft_id'])->first();
        $this->assertStringNotContainsString('Cliente sintético', $row->encrypted_payload);
        $this->assertEquals($this->body, json_decode(Crypt::decryptString($row->encrypted_payload), true));
        $this->assertSame($before, DB::table('invoicing_sales_invoices')->count());
        $this->assertSame(1, DB::table('sossaude_billing_drafts')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_conflicting_body_rejected_for_key_and_reference(): void
    {
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertCreated();
        $this->body['discount_amount'] = '1.00';
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertConflict();
        $this->headers['Idempotency-Key'] = (string) Str::uuid();
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertConflict();
    }

    public function test_foreign_product_and_clinical_fields_rejected_without_persistence(): void
    {
        $this->body['lines'][0]['diagnosis'] = 'must not be stored';
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertUnprocessable();
        unset($this->body['lines'][0]['diagnosis']);
        $other = \App\Models\Tenant::create(['name' => 'Outra empresa sintética', 'slug' => 'bridge-' . Str::uuid(),
            'nif' => (string) random_int(600000000, 699999999), 'is_active' => true]);
        DB::table('invoicing_products')->where('id', $this->body['lines'][0]['product_id'])->update(['tenant_id' => $other->id]);
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertUnprocessable();
        $this->assertSame(0, DB::table('sossaude_requests')->where('credential_id', $this->credential)->count());
    }

    public function test_expiry_revocation_scope_and_version_are_enforced(): void
    {
        $this->getJson(self::URL.'/connection', array_replace($this->headers, ['X-Bridge-Version' => '2']))->assertUnprocessable();
        DB::table('sossaude_credentials')->where('id', $this->credential)->update(['scopes' => '[]']);
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertForbidden();
        DB::table('sossaude_credentials')->where('id', $this->credential)->update(['expires_at' => now()->subSecond()]);
        $this->getJson(self::URL.'/connection', $this->headers)->assertUnauthorized();
        DB::table('sossaude_credentials')->where('id', $this->credential)->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->getJson(self::URL.'/connection', $this->headers)->assertUnauthorized();
    }

    public function test_failed_request_record_rolls_back_draft_and_allows_retry(): void
    {
        $fail = true;
        DB::connection()->beforeExecuting(function ($sql) use (&$fail) {
            if ($fail && str_starts_with($sql, 'insert into `sossaude_requests`')) {
                $fail = false;
                throw new \RuntimeException('simulated request persistence failure');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers);
            $this->fail('Expected persistence failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('simulated request persistence failure', $e->getMessage());
        }
        $this->assertSame(0, DB::table('sossaude_billing_drafts')->where('tenant_id', $this->tenant->id)->count());
        $this->postJson(self::URL.'/billing-drafts', $this->body, $this->headers)->assertCreated();
    }
}
