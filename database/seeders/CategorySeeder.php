<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Reference data, safe to run in every environment (including production) and to re-run.
     */
    public function run(): void
    {
        foreach (['Hardware', 'Software', 'Network', 'Email', 'Printer', 'Account/Access'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
