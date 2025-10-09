<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar configurações para JSON-LD Schema
        DB::table('system_settings')->insert([
            // Schema.org - Application Info
            [
                'key' => 'schema_app_name',
                'value' => 'SOSERP',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_app_description',
                'value' => 'Sistema de Gestão Empresarial Multi-Tenant para empresas em Angola',
                'type' => 'textarea',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_app_url',
                'value' => 'https://soserp.vip',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_app_category',
                'value' => 'BusinessApplication',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            
            // Pricing Info
            [
                'key' => 'schema_price',
                'value' => '0',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_currency',
                'value' => 'AOA',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_region',
                'value' => 'Angola',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            
            // Rating Info
            [
                'key' => 'schema_rating_value',
                'value' => '4.8',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_review_count',
                'value' => '150',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            
            // Creator/Organization
            [
                'key' => 'schema_creator_name',
                'value' => 'SOSERP',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'schema_creator_url',
                'value' => 'https://soserp.vip',
                'type' => 'text',
                'group' => 'schema',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('system_settings')
            ->where('group', 'schema')
            ->delete();
    }
};
