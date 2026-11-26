<?php

namespace Tests\Feature\Stok;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

class StokSinkronTest extends TestCase
{
    public function test_halaman_stok_punya_tombol_sinkron(): void
    {
        $this->get('/restock')->assertOk()->assertSee('Sinkronkan', false);
    }

    public function test_sinkron_memperbaiki_total_yang_tidak_konsisten(): void
    {
        // sengaja dibuat tidak konsisten: baik 90 + ringan 5 + berat 0 = 95, tapi total 100
        Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'SK1',
            'stok_total' => 100, 'stok_baik' => 90,
            'stok_rusak_ringan' => 5, 'stok_rusak_berat' => 0,
        ]);

        $this->post('/stok/sinkron')->assertRedirect();

        $b = Barang::first();
        $this->assertSame(95, (int) $b->stok_total, 'total dibetulkan jadi 95');
    }

    public function test_sinkron_ikut_menghitung_yang_sedang_keluar(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'SK2',
            'stok_total' => 100, 'stok_baik' => 60,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create(['nama_acara' => 'A', 'tanggal' => '2025-06-10', 'status' => 'aktif']);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 40]);

        // total 100, komponen 60+0+0+40 = 100 -> sudah benar
        $this->post('/stok/sinkron')->assertRedirect();

        $this->assertSame(100, (int) $b->fresh()->stok_total);
    }

    public function test_sinkron_tidak_bikin_minus(): void
    {
        Barang::create([
            'nama_barang' => 'Piring', 'satuan' => 'pcs', 'kode_qr' => 'SK3',
            'stok_total' => 5, 'stok_baik' => 10,   // tidak mungkin
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);

        $this->post('/stok/sinkron')->assertRedirect();

        $b = Barang::first();
        $this->assertSame(10, (int) $b->stok_total, 'total = baik + ringan + berat');
    }
}
