<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;

class QrPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_qr_menampilkan_setiap_barang_dengan_kodenya(): void
    {
        Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'SENDOK1',
            'stok_total' => 10, 'stok_baik' => 10, 'stok_rusak' => 0,
        ]);

        $res = $this->get('/qr');

        $res->assertOk();
        $res->assertSee('Sendok');
        $res->assertSee('SENDOK1');
    }

    public function test_scan_menampilkan_stok_barang_berdasarkan_kode(): void
    {
        Barang::create([
            'nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'GELAS1',
            'stok_total' => 50, 'stok_baik' => 40, 'stok_rusak' => 0,
        ]);

        $res = $this->get('/b/GELAS1');

        $res->assertOk();
        $res->assertSee('Gelas');
        $res->assertSee('50');
    }
}