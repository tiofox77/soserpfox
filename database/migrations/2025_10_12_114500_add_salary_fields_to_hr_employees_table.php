<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            // Remuneração e Benefícios
            $table->decimal('salary', 15, 2)->nullable()->after('status')->comment('Salário Base');
            $table->decimal('bonus', 15, 2)->nullable()->after('salary')->comment('Bônus/Prêmios');
            $table->decimal('transport_allowance', 15, 2)->nullable()->after('bonus')->comment('Subsídio de Transporte');
            $table->decimal('meal_allowance', 15, 2)->nullable()->after('transport_allowance')->comment('Subsídio de Alimentação');
        });
    }

    public function down()
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropColumn(['salary', 'bonus', 'transport_allowance', 'meal_allowance']);
        });
    }
};
