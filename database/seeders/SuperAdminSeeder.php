<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Criar Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => 'admin@soserp.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Super Admin criado com sucesso!');
        $this->command->info('Email: admin@soserp.com');
        $this->command->info('Password: password');
        $this->command->warn('⚠️  IMPORTANTE: Altere a senha em produção!');
    }
}
