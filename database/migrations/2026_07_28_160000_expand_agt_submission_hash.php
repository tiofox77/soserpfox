<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agt_submissions', function (Blueprint $table) {
            $table->text('hash')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('agt_submissions', function (Blueprint $table) {
            $table->string('hash')->nullable()->change();
        });
    }
};
