<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agt_cae_codes', function (Blueprint $table) {
            $table->id();
            // Código CAE — DS.120 Anexo 9.5 (INE Angola — CAE Rev. 2).
            // Pode ter de 1 a 5 dígitos (Secção, Divisão, Grupo, Classe, Subclasse).
            $table->string('code', 8)->unique();
            // Nível hierárquico: section|division|group|class|subclass
            $table->string('level', 16)->index();
            // Código do pai (para hierarquia)
            $table->string('parent_code', 8)->nullable()->index();
            // Descrição oficial INE
            $table->string('description', 500);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agt_cae_codes');
    }
};
