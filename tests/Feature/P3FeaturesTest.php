<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;

class P3FeaturesTest extends TestCase
{
    public function test_dashboard_menampilkan_ringkasan_hilang(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'selesai']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>50]);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>40,'jumlah_hilang'=>10]);

        $this->get('/')->assertOk()->assertSee('Ringkasan');
    }

    public function test_riwayat_per_barang_menampilkan_transaksi(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>30]);

        $res = $this->get("/barang/{$b->id}/riwayat")->assertOk();
        $res->assertSee('Sendok');
        $res->assertSee('Konser');
    }

    public function test_riwayat_per_barang_belum_ada_transaksi(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>10,'stok_baik'=>10,'stok_rusak'=>0]);

        $this->get("/barang/{$b->id}/riwayat")
            ->assertOk()
            ->assertSee('Belum ada riwayat');
    }

    public function test_riwayat_per_barang_404_jika_tidak_ada(): void
    {
        $this->get('/barang/99999/riwayat')->assertNotFound();
    }
}