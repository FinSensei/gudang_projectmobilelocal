<?php

namespace Tests\Feature\Barang;

use App\Models\Barang;
use Tests\TestCase;

class BarangStatusStokTest extends TestCase
{
    public function test_stok_habis_jika_baik_nol(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 100, 'stok_baik' => 0, 'stok_rusak' => 0]);
        $this->assertSame('habis', $b->statusStok());
    }

    public function test_stok_menipis_jika_baik_kurang_dari_10_persen(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S2', 'stok_total' => 100, 'stok_baik' => 5, 'stok_rusak' => 0]);
        $this->assertSame('menipis', $b->statusStok());
    }

    public function test_stok_aman_jika_baik_cukup(): void
    {
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S3', 'stok_total' => 100, 'stok_baik' => 50, 'stok_rusak' => 0]);
        $this->assertSame('aman', $b->statusStok());
    }

    public function test_stok_aman_jika_baik_persis_10_persen(): void
    {
        // 10 dari 100 = tepat 10% → bukan "kurang dari 10%" → aman
        $b = Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S4', 'stok_total' => 100, 'stok_baik' => 10, 'stok_rusak' => 0]);
        $this->assertSame('aman', $b->statusStok());
    }
}
