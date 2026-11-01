<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * All seeded accounts use the password "password".
 * These are local sample accounts only and must never exist in production.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->technician()->create([
            'name' => 'Tina Technician',
            'email' => 'tech1@example.com',
        ]);

        User::factory()->technician()->create([
            'name' => 'Tom Technician',
            'email' => 'tech2@example.com',
        ]);

        foreach (['Uche User', 'Ada User', 'Bola User'] as $i => $name) {
            User::factory()->create([
                'name' => $name,
                'email' => 'user'.($i + 1).'@example.com',
            ]);
        }
    }
}
