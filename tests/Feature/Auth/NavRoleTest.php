<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class NavRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();
    }

    public function test_nav_admin_memuat_link_beranda(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'a@x.id', 'password' => bcrypt('x'), 'role' => 'admin']);
        $this->actingAs($admin);
        $this->get('/')->assertOk()->assertSee('Beranda', false)->assertSee('Pengguna', false);
    }

    public function test_nav_pekerja_tidak_memuat_link_admin(): void
    {
        $pekerja = User::create(['name' => 'Pekerja', 'email' => 'p@x.id', 'password' => bcrypt('x'), 'role' => 'pekerja']);
        $this->actingAs($pekerja);
        $res = $this->get('/scan')->assertOk();
        $res->assertSee('Scan QR Barang');
        $res->assertDontSee('href="/acara"', false);
        $res->assertDontSee('href="/riwayat"', false);
        $res->assertDontSee('href="/users"', false);
    }
}
