<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE hr_overtime MODIFY start_time TIME NULL');
        DB::statement('ALTER TABLE hr_overtime MODIFY end_time TIME NULL');
    }

    public function down(): void
    {
        // Não reverter: poderia destruir registos válidos de horas directas.
    }
};
