<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class ScanKameraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();
    }

    public function test_pekerja_bisa_buka_halaman_scan_kamera(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        $this->get('/scan')->assertOk()->assertSee('Scan QR barang');
    }

    public function test_scan_kamera_punya_input_kode_manual(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        $this->get('/scan')->assertOk()->assertSee('kode manual');
    }

    public function test_admin_juga_bisa_buka_scan_kamera(): void
    {
        $a = User::create(['name'=>'A','email'=>'a@x.id','password'=>bcrypt('x'),'role'=>'admin']);
        $this->actingAs($a);

        $this->get('/scan')->assertOk();
    }

    public function test_scan_kamera_butuh_login(): void
    {
        $this->get('/scan')->assertRedirect('/login');
    }

    public function test_pekerja_tidak_bisa_buka_halaman_qr_admin(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        // /qr sekarang khusus admin (cetak kode QR)
        $this->get('/qr')->assertForbidden();
    }}
