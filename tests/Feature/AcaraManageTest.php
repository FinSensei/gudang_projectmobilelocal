<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Pengembalian;

class AcaraManageTest extends TestCase
{
    public function test_batalkan_acara_mengembalikan_stok(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>60,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>40]);

        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $a->refresh();
        $b->refresh();
        $this->assertSame('batal', $a->status, 'status acara jadi batal');
        $this->assertSame(100, $b->stok_baik, 'stok baik kembali 100');
    }

    public function test_batalkan_acara_selesai_ditolak(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'selesai']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $this->assertSame('selesai', $a->fresh()->status, 'acara selesai tidak bisa dibatalkan');
    }

    public function test_undo_pengembalian_mengembalikan_stok_dan_status(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>100,'stok_baik'=>60,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>40]);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>35,'jumlah_hilang'=>5]);
        $b->stok_baik = 95; $b->stok_total = 95; $b->save();
        $a->status = 'selesai'; $a->save();

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $b->refresh();
        $a->refresh();
        $this->assertSame(60, $b->stok_baik, 'stok kembali ke 60');
        $this->assertSame(100, $b->stok_total, 'total kembali 100');
        $this->assertSame('aktif', $a->status, 'acara kembali aktif');
    }

    public function test_undo_pengembalian_tidak_menghapus_data(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S4','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>10]);
        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>10,'jumlah_hilang'=>0]);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $this->assertNotNull($p->fresh(), 'pengembalian di-soft-delete, bukan dihapus permanen');
    }
}