<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Pengembalian;
use App\Services\PengembalianService;

class PengembalianSafetyTest extends TestCase
{

    public function test_kembali_melebihi_keluar_di_clamp_di_db(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>50,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>50]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'Petugas']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>80]); // kelebihan 30

        app(PengembalianService::class)->hitung($p);

        $detail = $p->detailPengembalian()->first();
        $this->assertSame(50, (int) $detail->jumlah_kembali, 'kembali harus di-clamp ke jumlah keluar');
        $this->assertSame(0, (int) $detail->jumlah_hilang, 'tidak ada yang hilang');
        $this->assertSame(100, $b->fresh()->stok_baik, 'baik naik 50 saja, bukan 80');
    }

    public function test_pengembalian_menolak_acara_yang_sudah_selesai(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>100,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'selesai']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            'kembali'   => [$b->id => 10],
        ])->assertSessionHasErrors('acara_ids');
    }

    public function test_pengembalian_menolak_jika_kembali_melebihi_keluar(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S3','stok_total'=>100,'stok_baik'=>50,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>50]);

        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            'kembali'   => [$b->id => 90],
        ])->assertSessionHasErrors('kembali');
    }
}