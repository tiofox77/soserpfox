<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O PROGRAMA DE REVENDEDORES (16/09/2026, RV-01).
 *
 * O revendedor é uma CONTA DA PLATAFORMA, com entrada e senha próprias — não é
 * um utilizador de nenhuma empresa. Traz empresas (pelo link, pelo código no
 * registo ou criando-as ele), gere-as a partir do seu portal e ganha a
 * comissão que o super admin lhe definir.
 *
 * As comissões nunca se apagam: anulam-se com motivo. E cada pagamento de uma
 * empresa só dá uma comissão (a chave única da origem).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resellers')) {
            Schema::create('resellers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('company_name')->nullable();
                $table->string('nif', 30)->nullable();
                $table->string('email')->unique();
                $table->string('phone', 30)->nullable();
                $table->string('province', 80)->nullable();
                $table->string('city', 120)->nullable();
                $table->string('website')->nullable();
                $table->string('password');
                // O código do link (/r/CÓDIGO) e do campo do registo. Nasce na aprovação.
                $table->string('code', 20)->nullable()->unique();
                // pendente | aprovado | suspenso | recusado
                $table->string('status', 20)->default('pendente')->index();
                // A regra da comissão (App\Services\Revenda\RegraDeComissao).
                $table->json('commission')->nullable();
                $table->string('bank_name', 120)->nullable();
                $table->string('iban', 60)->nullable();
                $table->text('motivation')->nullable();
                $table->text('internal_notes')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // A senha esquecida tem a sua tabela: a de sempre é chaveada pelo email,
        // e um revendedor com o mesmo email de um utilizador apagava-lhe o pedido.
        if (! Schema::hasTable('reseller_password_reset_tokens')) {
            Schema::create('reseller_password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'reseller_id')) {
                $table->foreignId('reseller_id')->nullable()->after('id')->constrained('resellers')->nullOnDelete();
                // link | codigo | revendedor
                $table->string('reseller_via', 20)->nullable()->after('reseller_id');
                $table->timestamp('reseller_linked_at')->nullable()->after('reseller_via');
            }
        });

        if (! Schema::hasTable('reseller_payouts')) {
            Schema::create('reseller_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
                $table->decimal('amount', 15, 2);
                $table->string('method', 30)->default('transferencia');
                $table->string('reference', 120)->nullable();
                $table->date('paid_at');
                $table->text('notes')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reseller_commissions')) {
            Schema::create('reseller_commissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('reseller_id')->constrained('resellers')->cascadeOnDelete();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                // order (pedido aprovado) | invoice (factura da subscrição paga)
                $table->string('origin_type', 20);
                $table->unsignedBigInteger('origin_id');
                $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $table->decimal('base_amount', 15, 2);
                $table->decimal('amount', 15, 2);
                // A regra tal como estava no momento: mudar a do revendedor não mexe nesta.
                $table->json('rule');
                // por_pagar | paga | anulada
                $table->string('status', 20)->default('por_pagar')->index();
                $table->foreignId('payout_id')->nullable()->constrained('reseller_payouts')->nullOnDelete();
                $table->text('cancel_reason')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['origin_type', 'origin_id'], 'reseller_commissions_origem_unica');
                $table->index(['reseller_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_commissions');
        Schema::dropIfExists('reseller_payouts');

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'reseller_id')) {
                $table->dropConstrainedForeignId('reseller_id');
                $table->dropColumn(['reseller_via', 'reseller_linked_at']);
            }
        });

        Schema::dropIfExists('reseller_password_reset_tokens');
        Schema::dropIfExists('resellers');
    }
};
