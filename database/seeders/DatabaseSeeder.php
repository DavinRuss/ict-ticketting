<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with safe local sample data.
     * Model events stay enabled: Ticket references are generated on "created".
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            TicketSeeder::class,
        ]);
    }
}
