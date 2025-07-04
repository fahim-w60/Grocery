<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('123456a@'),
            'role' => 'admin',
            'phone' => '012351415415',

        ]);

        User::create([
            'name' => 'Shopper User',
            'email' => 'shopper@gmail.com',
            'password' => Hash::make('123456a@'),
            'role' => 'shopper',
            'phone' => '01564154154158',
        ]);

        User::create([
            'name' => 'Shopper User',
            'email' => 'shopper1@gmail.com',
            'password' => Hash::make('123456a@'),
            'role' => 'shopper',
            'phone' => '015641541541',
        ]);

        
        User::create([
            'name' => 'User',
            'email' => 'user@gmail.com',
            'password' => Hash::make('123456a@'),
            'role' => 'user',
            'phone' => '0154185418541',
        ]);

        User::create([
           'name' => 'User',
            'email' => 'user1@gmail.com',
            'password' => Hash::make('123456a@'),
            'role' => 'user',
            'phone' => '014512521541',

        ]);



    }
}
