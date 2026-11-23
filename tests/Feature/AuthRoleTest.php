<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;

class AuthRoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Test auth butuh kondisi tamu/aksi login, bukan auto-login admin
        auth()->logout();
    }

    private function admin(): User
    {
        return User::create(['name'=>'Admin','email'=>'admin@gudang.id','password'=>bcrypt('rahasia123'),'role'=>'admin']);
    }

    private function pekerja(): User
    {
        return User::create(['name'=>'Pekerja','email'=>'pekerja@gudang.id','password'=>bcrypt('rahasia123'),'role'=>'pekerja']);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_login_halaman_tampil(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_admin_bisa_masuk(): void
    {
        $admin = $this->admin();

        $this->post('/login', ['email'=>'admin@gudang.id','password'=>'rahasia123'])
            ->assertRedirect('/');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_password_salah_ditolak(): void
    {
        $this->post('/login', ['email'=>'admin@gudang.id','password'=>'salah'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_pekerja_tidak_bisa_akses_halaman_admin(): void
    {
        $this->actingAs($this->pekerja());
        $this->get('/')->assertForbidden();
    }

    public function test_pekerja_bisa_scan_qr(): void
    {
        $this->actingAs($this->pekerja());
        $this->get('/scan')->assertOk();
    }

    public function test_admin_bisa_semua_halaman(): void
    {
        $this->actingAs($this->admin());
        foreach (['/', '/acara', '/proses', '/riwayat', '/restock', '/log', '/qr'] as $u) {
            $this->get($u)->assertOk();
        }
    }

    public function test_logout(): void
    {
        $this->actingAs($this->admin());
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}