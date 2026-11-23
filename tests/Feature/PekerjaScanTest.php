<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class PekerjaScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        auth()->logout();
    }

    public function test_pekerja_bisa_scan_barang(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        $this->get('/scan')->assertOk()->assertSee('Scan QR Barang');
        $this->assertAuthenticatedAs($p);
    }

    public function test_scan_route_menerima_kode_qr(): void
    {
        $p = User::create(['name'=>'P','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja']);
        $this->actingAs($p);

        // /b/{kode} ada di auth group, bukan admin
        $this->get('/b/anything')->assertNotFound(); // 404 (bukan 403) = boleh akses, datanya tidak ada
    }
}