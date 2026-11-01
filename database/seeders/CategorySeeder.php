<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const NAMES = ['Hardware', 'Software', 'Network', 'Email', 'Printer', 'Account/Access'];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Category::create(['name' => $name]);
        }
    }
}
