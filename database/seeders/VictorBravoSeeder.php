<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class VictorBravoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('email', 'vicbravo@delapengineering.com')->first();

        if ($user) {
            $user->update([
                'role' => User::ROLE_SUPER_ADMIN,
            ]);
            $this->command->info('Usuario Victor Bravo actualizado a super admin.');
        } else {
            User::create([
                'name' => 'Victor Bravo',
                'email' => 'vicbravo@delapengineering.com',
                'password' => 'password',
                'role' => User::ROLE_SUPER_ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $this->command->info('Usuario Victor Bravo creado como super admin.');
        }
    }
}

