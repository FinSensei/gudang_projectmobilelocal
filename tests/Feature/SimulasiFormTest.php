<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\Pengembalian;
use App\Services\PengembalianService;

class SimulasiFormTest extends TestCase
{
    public function test_ukuran_tiap_barang_tersimpan_berbeda(): void
    {
        // Form "Tambah barang" punya baris per barang; tiap baris punya ukuran sendiri.
        // Kalau semua baris share name="ukuran", hanya ukuran terakhir yang tersimpan
        // dan dipakai untuk SEMUA barang -> bug.
        $this->post('/barang', [
            'nama'    => ['Sendok', 'Garpu'],
            'jumlah'  => [10, 20],
            'ukuran'  => ['21 cm', '20 cm'],
        ])->assertRedirect();

        $sendok = Barang::where('nama_barang', 'Sendok')->first();
        $garpu = Barang::where('nama_barang', 'Garpu')->first();

        $this->assertSame('21 cm', $sendok->ukuran, 'ukuran Sendok harus 21 cm');
        $this->assertSame('20 cm', $garpu->ukuran, 'ukuran Garpu harus 20 cm');
    }

    public function test_tanpa_ukuran_tetap_boleh(): void
    {
        $this->post('/barang', [
            'nama'    => ['Sendok'],
            'jumlah'  => [10],
            'ukuran'  => [''],
        ])->assertRedirect();

        $this->assertNull(Barang::where('nama_barang','Sendok')->first()->ukuran);
    }

    public function test_return_kedua_kali_ditolak_karena_acara_sudah_selesai(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>60,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>40]);

        // return pertama
        $p1 = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'P']);
        $p1->acara()->attach($a->id);
        $p1->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>40]);
        app(PengembalianService::class)->hitung($p1);
        $this->assertSame('selesai', $a->fresh()->status);

        // return kedua (acara sudah selesai) harus ditolak
        $this->post('/pengembalian', [
            'acara_ids' => [$a->id],
            'kembali'   => [$b->id => 40],
        ])->assertSessionHasErrors('acara_ids');

        // stok tidak berubah
        $b->refresh();
        $this->assertSame(100, $b->stok_baik, 'stok tidak bertambah 2x');
    }

    public function test_kembali_kosong_berarti_semua_hilang(): void
    {
        $b = Barang::create(['nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1','stok_total'=>100,'stok_baik'=>60,'stok_rusak'=>0]);
        $a = Acara::create(['nama_acara'=>'Konser','tanggal'=>'2026-01-01','status'=>'aktif']);
        $a->detailAcara()->create(['barang_id'=>$b->id,'jumlah_keluar'=>40]);
        $b->update(['stok_baik'=>60]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2026-01-02','dicatat_oleh'=>'P']);
        $p->acara()->attach($a->id);
        $p->detailPengembalian()->create(['barang_id'=>$b->id,'jumlah_kembali'=>0]);
        app(PengembalianService::class)->hitung($p);

        $b->refresh();
        $this->assertSame(60, $b->stok_baik, 'baik tidak naik');
        $this->assertSame(60, $b->stok_total, 'total 100-40 hilang');
        // cek hilang tercatat
        $this->assertSame(40, (int) $p->detailPengembalian()->first()->jumlah_hilang);
    }
}