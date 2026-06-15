<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the single application user from env (no registration flow).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SEED_USER_EMAIL', 'admin@arcturus.test')],
            [
                'name' => 'Admin',
                'password' => Hash::make(env('SEED_USER_PASSWORD', 'password')),
            ],
        );
    }
}
