<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;

class BarangTambahHapusTest extends TestCase
{
    use RefreshDatabase;

    public function test_tambah_stok_menambah_total_dan_baik(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);

        $this->post("/barang/{$b->id}/tambah", ['jumlah'=>50])->assertRedirect();

        $b->refresh();
        $this->assertSame(150, $b->stok_total, 'total +50');
        $this->assertSame(150, $b->stok_baik, 'baik +50');
    }

    public function test_tambah_stok_menolak_jumlah_nol(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);

        $this->post("/barang/{$b->id}/tambah", ['jumlah'=>0])->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(100, $b->stok_total, 'stok tidak berubah');
    }

    public function test_hapus_total_ditolak_jika_sedang_digunakan(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>100,'stok_baik'=>90,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->delete("/barang/{$b->id}")->assertRedirect();

        $this->assertDatabaseHas('barang', ['id'=>$b->id, 'deleted_at'=>null]);
    }

    public function test_hapus_total_berhasil_jika_tidak_digunakan(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S4','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);

        $this->delete("/barang/{$b->id}")->assertRedirect();

        $this->assertSoftDeleted('barang', ['id'=>$b->id]);
    }
}