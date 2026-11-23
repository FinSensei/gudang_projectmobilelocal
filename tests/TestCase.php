<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Default: semua test berjalan sebagai admin.
        // Test yang butuh tamu/pekerja bisa override dengan actingAs() / logout().
        if (!auth()->check()) {
            $this->actingAs($this->makeAdmin());
        }
    }

    protected function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Test',
            'email'    => 'admin@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    protected function makePekerja(): User
    {
        return User::create([
            'name'     => 'Pekerja Test',
            'email'    => 'pekerja@test.local',
            'password' => bcrypt('password'),
            'role'     => 'pekerja',
        ]);
    }
}