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
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->boolean('portal_access')->default(false)->after('last_login_at');
            $table->timestamp('password_changed_at')->nullable()->after('portal_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoicing_clients', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'remember_token',
                'last_login_at',
                'portal_access',
                'password_changed_at'
            ]);
        });
    }
};
