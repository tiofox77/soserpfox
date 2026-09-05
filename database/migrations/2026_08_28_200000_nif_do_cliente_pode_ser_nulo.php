<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Um cliente sem contribuinte deixa de ter um contribuinte falso.
 *
 * O `nif` era NOT NULL, e o produto tapava isso com o `999999999` — o NIF de
 * consumidor final. Parecia inofensivo até se juntar ao índice único
 * `(tenant_id, nif)`: dois clientes de balcão sem contribuinte colidiam.
 *
 * A API do PWA resolvia a colisão da pior maneira possível — em silêncio.
 * Encontrava o Consumidor Final com esse NIF e devolvia-o como se fosse o
 * cliente novo. O operador criava "Maria da esquina", o aparelho dizia que
 * estava feito, e o que ficava lá era o Consumidor Final. Sem erro, sem fila
 * parada, sem nada. Todo o cliente de balcão sem contribuinte era engolido.
 *
 * NULO É A RESPOSTA CERTA. Não tem contribuinte, e é isso que fica escrito.
 * O MySQL aceita muitos NULL num índice único, por isso vinte clientes de
 * balcão passam a caber — e um NIF a sério continua a ser único, que é o que
 * o índice existe para garantir.
 *
 * OS DADOS QUE JÁ LÁ ESTÃO NÃO SE TOCAM. Um Consumidor Final com 999999999 é
 * um registo legítimo e há facturas emitidas em nome dele; convertê-lo a nulo
 * agora mudava documentos passados. Só o que nascer daqui para a frente é que
 * fica nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sem doctrine/dbal, `change()` não está disponível de forma fiável —
        // e um ALTER escrito à mão diz exactamente o que faz.
        DB::statement('ALTER TABLE `invoicing_clients` MODIFY `nif` VARCHAR(255) NULL');
    }

    public function down(): void
    {
        // A volta atrás precisa de um valor para as linhas nulas: sem ele o
        // ALTER falha. Volta a pôr o marcador de não identificado.
        DB::statement("UPDATE `invoicing_clients` SET `nif` = '999999999' WHERE `nif` IS NULL");
        DB::statement('ALTER TABLE `invoicing_clients` MODIFY `nif` VARCHAR(255) NOT NULL');
    }
};
