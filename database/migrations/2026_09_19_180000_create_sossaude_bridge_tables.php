<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sossaude_credentials', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id')->index();
            $t->string('name');
            $t->string('token_hash', 64)->unique();
            $t->json('scopes');
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
        });
        Schema::create('sossaude_billing_drafts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('credential_id');
            $t->string('source', 30);
            $t->uuid('source_reference');
            $t->string('body_hash', 64);
            $t->longText('encrypted_payload');
            $t->string('status', 20)->default('draft');
            $t->timestamps();
            $t->unique(['tenant_id', 'source', 'source_reference'], 'sossaude_draft_reference_unique');
        });
        Schema::create('sossaude_requests', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('tenant_id');
            $t->unsignedBigInteger('credential_id');
            $t->string('operation', 40);
            $t->uuid('idempotency_key');
            $t->string('body_hash', 64);
            $t->unsignedBigInteger('draft_id');
            $t->unsignedSmallInteger('http_status');
            $t->json('response');
            $t->timestamps();
            $t->unique(['tenant_id', 'credential_id', 'operation', 'idempotency_key'], 'sossaude_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sossaude_requests');
        Schema::dropIfExists('sossaude_billing_drafts');
        Schema::dropIfExists('sossaude_credentials');
    }
};
