<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'     => 'Administrator',
                'email'    => 'admin@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_ADMIN,
            ]
        );

        // Employee
        User::updateOrCreate(
            ['username' => 'employee'],
            [
                'name'     => 'Employee',
                'email'    => 'employee@example.com',
                'password' => Hash::make('password'),
                'role'     => User::ROLE_EMPLOYEE,
            ]
        );
    }
}
