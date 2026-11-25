<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Models\LogAktivitas;
use Illuminate\Support\Facades\Schema;

class KeteranganRusakTest extends TestCase
{
    private function b(): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'KR'.uniqid(),
            'stok_total'=>100,'stok_baik'=>100,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    private function acaraAktif(Barang $b, int $keluar = 10): Acara
    {
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>$keluar]);
        return $a;
    }

    // ================= 1. Stok: keterangan saat tandai rusak/hilang =================
    public function test_form_tandai_rusak_punya_input_keterangan(): void
    {
        $this->b();

        $this->get('/restock')
            ->assertOk()
            ->assertSee('name="keterangan"', false)
            ->assertSee('Keterangan', false);
    }

    public function test_keterangan_rusak_masuk_ke_log_aktivitas(): void
    {
        $b = $this->b();

        $this->post('/rusak', [
            'barang_id'   => $b->id,
            'jumlah'      => 3,
            'jenis'       => 'rusak',
            'tingkat'     => 'berat',
            'keterangan'  => 'Pegangannya patah',
        ])->assertRedirect();

        $log = LogAktivitas::where('aksi', 'rusak')->latest('id')->first();

        $this->assertNotNull($log, 'log rusak harus dibuat');
        $this->assertStringContainsString('Pegangannya patah', (string) $log->keterangan);
    }

    public function test_keterangan_hilang_masuk_ke_log(): void
    {
        $b = $this->b();

        $this->post('/rusak', [
            'barang_id'   => $b->id,
            'jumlah'      => 2,
            'jenis'       => 'hilang',
            'keterangan'  => 'Tidak kembali dari acara',
        ])->assertRedirect();

        $log = LogAktivitas::where('aksi', 'hilang')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Tidak kembali dari acara', (string) $log->keterangan);
    }

    // ================= 2. Pengembalian: keterangan per barang =================
    public function test_tabel_detail_pengembalian_punya_kolom_keterangan(): void
    {
        $this->assertTrue(Schema::hasColumn('detail_pengembalian', 'keterangan'));
    }

    public function test_form_pengembalian_punya_input_keterangan(): void
    {
        $b = $this->b();
        $this->acaraAktif($b);

        $this->get('/pengembalian')
            ->assertOk()
            ->assertSee('name="keterangan[', false);
    }

    public function test_keterangan_pengembalian_tersimpan(): void
    {
        $b = $this->b();
        $a = $this->acaraAktif($b);

        $this->post('/pengembalian', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 2],
            'rusak_berat'  => [$b->id => 1],
            'hilang'       => [$b->id => 0],
            'keterangan'   => [$b->id => 'Gagang bengkok'],
        ])->assertRedirect();

        $d = DetailPengembalian::first();

        $this->assertNotNull($d);
        $this->assertSame('Gagang bengkok', $d->keterangan);
    }

    public function test_keterangan_tampil_di_review(): void
    {
        $b = $this->b();
        $a = $this->acaraAktif($b);

        $this->post('/pengembalian/preview', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 2],
            'rusak_berat'  => [$b->id => 0],
            'hilang'       => [$b->id => 1],
            'keterangan'   => [$b->id => 'Mangkuk pecah'],
        ])
            ->assertOk()
            ->assertSee('Mangkuk pecah');
    }

    public function test_keterangan_tidak_wajib(): void
    {
        $b = $this->b();
        $a = $this->acaraAktif($b);

        $this->post('/pengembalian', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 1],
            'rusak_berat'  => [$b->id => 0],
            'hilang'       => [$b->id => 0],
        ])->assertRedirect();

        $d = DetailPengembalian::first();
        $this->assertNotNull($d);
        $this->assertNull($d->keterangan);
    }
}