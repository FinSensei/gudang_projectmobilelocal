<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gudang.id'],
            ['name' => 'Admin Gudang', 'password' => Hash::make('admin123'), 'role' => 'admin']
        );

        User::updateOrCreate(
            ['email' => 'pekerja@gudang.id'],
            ['name' => 'Pekerja Gudang', 'password' => Hash::make('pekerja123'), 'role' => 'pekerja']
        );
    }
}