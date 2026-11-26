<?php

namespace Tests\Feature\Stok;

use App\Models\Barang;
use Tests\TestCase;

class StokRusakSplitTest extends TestCase
{
    public function test_barang_menyimpan_rusak_ringan_dan_berat(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'SR1',
            'stok_total' => 100, 'stok_baik' => 90,
            'stok_rusak_ringan' => 6, 'stok_rusak_berat' => 4,
        ]);
        $b->refresh();

        $this->assertSame(6, $b->stok_rusak_ringan, 'rusak ringan tersimpan');
        $this->assertSame(4, $b->stok_rusak_berat, 'rusak berat tersimpan');
    }

    public function test_accessor_stok_rusak_adalah_ringan_plus_berat(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'SR2',
            'stok_total' => 100, 'stok_baik' => 80,
            'stok_rusak_ringan' => 12, 'stok_rusak_berat' => 8,
        ]);

        $this->assertSame(20, $b->stok_rusak, 'stok_rusak = ringan + berat');
    }

    public function test_invariant_empat_komponen(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'SR3',
            'stok_total' => 100, 'stok_baik' => 90,
            'stok_rusak_ringan' => 6, 'stok_rusak_berat' => 4,
        ]);

        $inv = $b->stok_baik + $b->stok_rusak_ringan + $b->stok_rusak_berat;

        $this->assertSame(100, $inv, 'baik + ringan + berat = total (belum ada yang keluar)');
    }

    public function test_default_rusak_nol_kalau_tidak_diisi(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Piring', 'satuan' => 'pcs', 'kode_qr' => 'SR4',
            'stok_total' => 50, 'stok_baik' => 50,
        ]);

        $this->assertSame(0, $b->stok_rusak_ringan);
        $this->assertSame(0, $b->stok_rusak_berat);
        $this->assertSame(0, $b->stok_rusak);
    }
}
