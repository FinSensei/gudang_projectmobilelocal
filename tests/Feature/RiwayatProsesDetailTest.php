<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Pengembalian;

class RiwayatProsesDetailTest extends TestCase
{

    public function test_riwayat_list_menampilkan_tombol_detail(): void
    {
        $barang = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $acara = Acara::create(['nama_acara'=>'Pernikahan','tanggal'=>'2026-01-01','status'=>'selesai']);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($acara->id);
        $p->detailPengembalian()->create(['barang_id'=>$barang->id,'jumlah_kembali'=>50,'jumlah_hilang'=>0]);

        $this->get('/riwayat')->assertOk()->assertSee('Detail');
    }

    public function test_riwayat_detail_menampilkan_rincian_barang(): void
    {
        $barang = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $acara = Acara::create(['nama_acara'=>'Pernikahan','tanggal'=>'2026-01-01','status'=>'selesai']);
        $acara->detailAcara()->create(['barang_id'=>$barang->id,'jumlah_keluar'=>50]);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($acara->id);
        $p->detailPengembalian()->create(['barang_id'=>$barang->id,'jumlah_kembali'=>48,'jumlah_hilang'=>2]);

        $this->get("/riwayat/{$p->id}")
            ->assertOk()
            ->assertSee('Detail pengembalian')
            ->assertSee('Sendok');
    }

    public function test_proses_detail_menampilkan_rincian_barang(): void
    {
        $barang = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$barang->id,'jumlah_keluar'=>30]);

        $this->get("/proses/{$a->id}")
            ->assertOk()
            ->assertSee('Sendok');
    }

    public function test_proses_list_menampilkan_tombol_detail(): void
    {
        $barang = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$barang->id,'jumlah_keluar'=>30]);

        $this->get('/proses')->assertOk()->assertSee('Detail');
    }
}