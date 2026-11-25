<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Services\PengembalianService;

class PengembalianKembaliTest extends TestCase
{

    // BUG #1: barang dari acara yang TIDAK dicentang tidak boleh diproses
    public function test_pengembalian_tidak_memproses_barang_dari_acara_yang_tidak_dipilih(): void
    {
        $sendok = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>1000,'stok_baik'=>1000,'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0]);
        $gelas  = Barang::create(['nama_barang'=>'Gelas','satuan'=>'pcs','kode_qr'=>'G1','stok_total'=>500,'stok_baik'=>500,'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0]);

        $a = Acara::create(['nama_acara'=>'A','status'=>'aktif']);
        $b = Acara::create(['nama_acara'=>'B','status'=>'aktif']);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$sendok->id,'jumlah_keluar'=>300]);
        $sendok->stok_baik = 700; $sendok->save();
        DetailAcara::create(['acara_id'=>$b->id,'barang_id'=>$gelas->id,'jumlah_keluar'=>200]);
        $gelas->stok_baik = 300; $gelas->save();

        // operator hanya centang A, tapi form ikut mengirim kembali milik B (gelas)
        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            // sendok: 300 keluar, 20 hilang -> baik otomatis 280
            'hilang'    => [$sendok->id => 20, $gelas->id => 999],
            'tanggal'   => '2026-11-21',
        ])->assertRedirect('/');

        $sendok->refresh();
        $gelas->refresh();
        $this->assertSame(980, $sendok->stok_total, 'sendok hilang 20');
        $this->assertSame(980, $sendok->stok_baik);
        $this->assertSame(500, $gelas->stok_total, 'gelas TIDAK boleh berubah (B tidak dipilih)');
        $this->assertSame(300, $gelas->stok_baik, 'gelas baik tetap 300');
        $this->assertSame('aktif', $b->refresh()->status, 'B tetap aktif');
    }

    // BUG #2: kembali > keluar tidak boleh menaikkan stok (hilang tidak boleh negatif)
    public function test_kembali_lebih_dari_keluar_tidak_menaikkan_stok(): void
    {
        $sendok = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S2','stok_total'=>100,'stok_baik'=>50,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'A','status'=>'aktif']);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$sendok->id,'jumlah_keluar'=>50]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2026-11-21']);
        $p->acara()->attach($a->id);
        DetailPengembalian::create(['pengembalian_id'=>$p->id,'barang_id'=>$sendok->id,'jumlah_kembali'=>60,'jumlah_rusak_ringan'=>0,'jumlah_rusak_berat'=>0]);

        app(PengembalianService::class)->hitung($p->refresh());

        $sendok->refresh();
        $this->assertSame(100, $sendok->stok_total, 'stok tidak boleh naik jadi 110');
        $this->assertSame(100, $sendok->stok_baik, 'baik tidak boleh naik jadi 110');
    }
}