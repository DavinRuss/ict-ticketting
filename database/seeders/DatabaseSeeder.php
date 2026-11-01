<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay on: ticket references are generated in Ticket's created event.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        if (app()->isProduction()) {
            $this->command?->warn('Skipping sample accounts in production. Use php artisan app:create-admin instead.');

            return;
        }

        // Local sample accounts only; all use the password "password".
        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $technician = User::factory()->technician()->create([
            'name' => 'Tech User',
            'email' => 'tech@example.com',
        ]);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $categories = Category::pluck('id', 'name');
        $tickets = app(TicketService::class);

        $created = [];

        foreach ([
            ['Laptop will not power on', 'Hardware', TicketPriority::High],
            ['Cannot connect to office Wi-Fi', 'Network', TicketPriority::Medium],
            ['Outlook keeps asking for my password', 'Email', TicketPriority::Medium],
            ['Second-floor printer jams on every page', 'Printer', TicketPriority::Low],
            ['Need access to the finance shared drive', 'Account/Access', TicketPriority::Urgent],
        ] as [$title, $category, $priority]) {
            $created[] = $tickets->create($user, [
                'title' => $title,
                'description' => "Sample ticket for local development: {$title}.",
                'category_id' => $categories[$category],
                'priority' => $priority->value,
            ]);
        }

        // Walk two samples through the workflow so the admin screens show history.
        $tickets->assign($created[0], $technician, $admin);
        $tickets->changeStatus($created[0], TicketStatus::InProgress, $admin);
        $tickets->assign($created[1], $technician, $admin);
    }
}
