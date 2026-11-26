<?php

namespace Tests\Feature\Barang;

use App\Models\Barang;
use App\Models\Kategori;
use Tests\TestCase;

class KategoriFilterTest extends TestCase
{
    public function test_filter_barang_menurut_kategori(): void
    {
        $k = Kategori::create(['nama_kategori' => 'Peralatan Makan']);
        Barang::create(['kategori_id' => $k->id, 'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S1', 'stok_total' => 10, 'stok_baik' => 10, 'stok_rusak' => 0]);
        Barang::create(['nama_barang' => 'Kursi', 'satuan' => 'pcs', 'kode_qr' => 'K1', 'stok_total' => 10, 'stok_baik' => 10, 'stok_rusak' => 0]);

        $this->get('/?kategori='.$k->id)->assertOk()->assertSee('Sendok')->assertDontSee('Kursi');
    }

    public function test_low_stock_menampilkan_peringatan(): void
    {
        Barang::create(['nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'S2', 'stok_total' => 100, 'stok_baik' => 5, 'stok_rusak' => 0]);
        Barang::create(['nama_barang' => 'Gelas', 'satuan' => 'pcs', 'kode_qr' => 'G1', 'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0]);

        $res = $this->get('/')->assertOk();
        $res->assertSee('Stok habis / menipis');
        $res->assertSee('Sendok');
    }
}
