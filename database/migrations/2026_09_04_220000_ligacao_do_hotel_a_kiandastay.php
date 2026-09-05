<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ligação do módulo de hotel ao KiandaStay.
 *
 * O KiandaStay é o site de reservas (o motor); o módulo de hotel é o PMS, onde
 * a casa gere a estadia. Uma reserva feita no site tem de aparecer sozinha na
 * recepção — com hóspede, datas, tipo de quarto e preço — para se poder fazer
 * check-in, folio e factura sem ninguém copiar nada à mão.
 *
 * Três coisas ficam gravadas:
 *
 *   1. UMA LINHA POR EMPRESA com as credenciais. Os segredos (chave da API e
 *      segredo do webhook) ficam CIFRADOS, como na ligação ao Meta;
 *
 *   2. A ORIGEM 'kiandastay' na reserva. Sem um valor próprio, estas reservas
 *      confundiam-se com as do site da própria casa ('website') e deixava de se
 *      poder medir quanto vale o canal;
 *
 *   3. A REFERÊNCIA EXTERNA. É ela que impede a mesma reserva de entrar duas
 *      vezes — o webhook do KiandaStay não tem repetição controlada, e uma
 *      entrega repetida criaria uma segunda reserva do mesmo hóspede.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hotel_ligacao_kiandastay')) {
            Schema::create('hotel_ligacao_kiandastay', function (Blueprint $t) {
                $t->id();

                // Uma só ligação por empresa.
                $t->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();

                $t->boolean('activa')->default(false);

                $t->string('base_url')->nullable();

                // Cifrados em repouso pelo cast do modelo.
                $t->text('api_key')->nullable();
                $t->text('webhook_secret')->nullable();

                // O webhook registado lá, para se poder apagar depois.
                $t->string('webhook_id', 40)->nullable();

                // Qual dos hotéis do site é esta empresa.
                $t->unsignedBigInteger('property_id')->nullable();
                $t->string('property_name')->nullable();

                // tipo de quarto do site => tipo de quarto desta casa
                $t->json('mapa_tipos')->nullable();

                // Em que estado entra uma reserva nova. Uma casa que confirma à
                // mão quer 'pending'; quem confia no site quer 'confirmed'.
                $t->string('estado_inicial', 20)->default('pending');

                // Criar a ficha de hóspede a partir do que o site enviar.
                $t->boolean('criar_hospede')->default(true);

                $t->timestamp('ultimo_evento_em')->nullable();
                $t->unsignedInteger('eventos_recebidos')->default(0);
                $t->text('ultimo_erro')->nullable();

                $t->timestamps();
            });
        }

        // ── A referência externa na reserva ────────────────────────────
        if (Schema::hasTable('hotel_reservations')) {
            Schema::table('hotel_reservations', function (Blueprint $t) {
                if (! Schema::hasColumn('hotel_reservations', 'external_source')) {
                    $t->string('external_source', 40)->nullable()->after('source');
                }

                if (! Schema::hasColumn('hotel_reservations', 'external_id')) {
                    $t->string('external_id', 64)->nullable()->after('external_source');
                }

                // O CODIGO CABE. O do KiandaStay e 'OKB-' mais oito (doze
                // caracteres) e a coluna tinha dez: gravar a reserva rebentava
                // com "Data too long". E o codigo tem de ser este, porque e o
                // que o hospede traz na mao e pelo qual a recepcao procura.
                $t->string('confirmation_code', 32)->nullable()->change();
            });

            // O índice é o que garante que a mesma reserva do site não entra
            // duas vezes. Por EMPRESA, como todos os índices desta casa.
            $indices = collect(DB::select('SHOW INDEX FROM hotel_reservations'))->pluck('Key_name')->unique();

            if (! $indices->contains('hotel_reservations_externa_unica')) {
                Schema::table('hotel_reservations', function (Blueprint $t) {
                    $t->unique(['tenant_id', 'external_source', 'external_id'], 'hotel_reservations_externa_unica');
                });
            }
        }

        $this->definirOrigens(true);
    }

    public function down(): void
    {
        if (Schema::hasTable('hotel_reservations')) {
            $indices = collect(DB::select('SHOW INDEX FROM hotel_reservations'))->pluck('Key_name')->unique();

            if ($indices->contains('hotel_reservations_externa_unica')) {
                Schema::table('hotel_reservations', function (Blueprint $t) {
                    $t->dropUnique('hotel_reservations_externa_unica');
                });
            }

            foreach (['external_source', 'external_id'] as $coluna) {
                if (Schema::hasColumn('hotel_reservations', $coluna)) {
                    Schema::table('hotel_reservations', fn (Blueprint $t) => $t->dropColumn($coluna));
                }
            }

            // Ninguém fica com uma origem que a coluna deixa de aceitar.
            DB::table('hotel_reservations')->where('source', 'kiandastay')->update(['source' => 'other']);
        }

        $this->definirOrigens(false);

        Schema::dropIfExists('hotel_ligacao_kiandastay');
    }

    /** A lista de origens de uma reserva, com ou sem o canal novo. */
    private function definirOrigens(bool $comKiandaStay): void
    {
        if (! Schema::hasTable('hotel_reservations') || ! Schema::hasColumn('hotel_reservations', 'source')) {
            return;
        }

        $origens = ['direct', 'website', 'booking', 'airbnb', 'phone', 'email', 'walk_in', 'other'];

        if ($comKiandaStay) {
            array_splice($origens, 2, 0, 'kiandastay');
        }

        $lista = implode(',', array_map(fn ($o) => "'" . $o . "'", $origens));

        DB::statement(
            "ALTER TABLE hotel_reservations MODIFY COLUMN source ENUM({$lista}) NOT NULL DEFAULT 'direct'"
        );
    }
};
