<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deliberately factory-free: this seeder is meant to run in production
 * (php artisan db:seed) to set up the one real admin account and the
 * default Free plan - not to generate fake/dummy data. Model factories
 * pull in fakerphp/faker, a dev-only dependency that isn't installed when
 * composer install runs with --no-dev, so using them here would crash on
 * any production install.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('role', 'admin')->exists()) {
            $admin = new User([
                'first_name' => 'Proudify',
                'last_name' => 'Admin',
                'organization_name' => 'Proudify',
                'email' => 'admin@proudify.in',
                'phone' => '9999999999',
                'password' => bcrypt('ChangeThisPassword123!'),
            ]);
            $admin->forceFill(['role' => 'admin', 'status' => 'active'])->save();
        }

        if (! Subscription::where('name', 'Free')->exists()) {
            Subscription::create([
                'name' => 'Free',
                'description' => 'Get started with basic certificate issuance.',
                'certificates_per_month' => 10,
                'certificates_per_year' => 120,
                'users_per_month' => 10,
                'users_per_year' => 120,
                'cost_month_inr' => 0,
                'cost_year_inr' => 0,
                'cost_month_usd' => 0,
                'cost_year_usd' => 0,
                'is_default_free_plan' => true,
                'is_active' => true,
                'sort_order' => 0,
            ]);
        }
    }
}
