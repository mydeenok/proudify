<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'first_name' => 'Proudify',
            'last_name' => 'Admin',
            'organization_name' => 'Proudify',
            'email' => 'admin@proudify.test',
            'password' => bcrypt('password'),
        ]);

        // User::factory()->create([
        //     'first_name' => 'Demo',
        //     'last_name' => 'User',
        //     'organization_name' => 'Acme University',
        //     'email' => 'demo@proudify.test',
        //     'password' => bcrypt('password'),
        // ]);

        Subscription::factory()->free()->create([
            'name' => 'Free',
            'description' => 'Get started with basic certificate issuance.',
            'sort_order' => 0,
        ]);

        // Subscription::factory()->create([
        //     'name' => 'Professional',
        //     'description' => 'For growing organizations issuing certificates regularly.',
        //     'certificates_per_month' => 500,
        //     'certificates_per_year' => 6000,
        //     'users_per_month' => 500,
        //     'users_per_year' => 6000,
        //     'cost_month_inr' => 1999,
        //     'cost_year_inr' => 19999,
        //     'cost_month_usd' => 29,
        //     'cost_year_usd' => 299,
        //     'sort_order' => 1,
        // ]);
    }
}
