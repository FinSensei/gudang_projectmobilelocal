<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;

class BarangKurangiTest extends TestCase
{

    public function test_kurangi_mengurangi_stok_total_dan_baik(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);

        $this->post("/barang/{$b->id}/kurangi", ['jumlah'=>10])->assertRedirect();

        $b->refresh();
        $this->assertSame(90, $b->stok_total, 'total berkurang 10');
        $this->assertSame(90, $b->stok_baik, 'baik berkurang 10');
    }

    public function test_kurangi_menolak_jika_melebihi_stok_baik(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->post("/barang/{$b->id}/kurangi", ['jumlah'=>200])->assertRedirect();

        $b->refresh();
        $this->assertSame(10, $b->stok_total, 'stok tidak berubah');
        $this->assertSame(10, $b->stok_baik, 'baik tidak berubah');
    }

    public function test_kurangi_menolak_jika_melebihi_stok_baik_tapi_total_lebih_besar(): void
    {
        // 30 sedang di event, baik 70, total 100. Kurangi 80 > baik 70 → tolak.
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>100,'stok_baik'=>70,'stok_rusak'=>0]);

        $this->post("/barang/{$b->id}/kurangi", ['jumlah'=>80])->assertRedirect();

        $b->refresh();
        $this->assertSame(100, $b->stok_total);
        $this->assertSame(70, $b->stok_baik);
    }
}