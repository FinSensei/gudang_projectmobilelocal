<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;

class BarangManageTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_menyaring_barang_menurut_nama(): void
    {
        Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);
        Barang::create(['nama_barang'=>'Gelas','satuan'=>'pcs','kode_qr'=>'G1','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->get('/?q=send')->assertOk()->assertSee('Sendok')->assertDontSee('Gelas');
    }

    public function test_update_mengubah_nama_dan_lokasi_tanpa_mengubah_stok(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0,'lokasi'=>'Rak A']);

        $this->put("/barang/{$b->id}", [
            'nama_barang' => 'Sendok Besar',
            'lokasi'      => 'Rak B',
            'satuan'      => 'pcs',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame('Sendok Besar', $b->nama_barang);
        $this->assertSame('Rak B', $b->lokasi);
        $this->assertSame(100, $b->stok_total, 'stok tidak boleh berubah lewat edit');
    }

    public function test_hapus_soft_delete_dan_tidak_tampil_di_daftar(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->delete("/barang/{$b->id}")->assertRedirect();
        $this->assertSoftDeleted($b);
        $this->assertSame(0, Barang::count(), 'barang tidak lagi aktif');

        // pastikan tidak muncul di daftar dashboard (cek link edit unik barang ini)
        $this->get('/')->assertOk()->assertDontSee("/barang/{$b->id}/edit", false);
    }
}