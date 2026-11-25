<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Services\PengembalianService;

class Audit2Test extends TestCase
{
    private function b(int $baik = 90, int $total = 100): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'A2'.uniqid(),
            'stok_total'=>$total,'stok_baik'=>$baik,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    private function acara(Barang $b, int $keluar, string $status = 'aktif'): Acara
    {
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>$status,'penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>$keluar]);
        return $a;
    }

    private function kembali(Acara $a, Barang $b, array $isi): Pengembalian
    {
        $p = Pengembalian::create(['tanggal_pulang'=>'2025-06-11','dicatat_oleh'=>'P','catatan'=>null]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id'=>$p->id,'barang_id'=>$b->id,
            'jumlah_kembali'=>$isi['baik'],
            'jumlah_rusak_ringan'=>$isi['ringan'],
            'jumlah_rusak_berat'=>$isi['berat'],
            'jumlah_hilang'=>$isi['hilang'],
        ]);
        app(PengembalianService::class)->hitung($p->fresh());
        return $p;
    }

    private function invariant(Barang $b): int
    {
        $keluar = (int) DetailAcara::whereHas('acara', fn ($q) => $q->where('status','aktif'))
            ->where('barang_id', $b->id)->sum('jumlah_keluar');
        return (int) $b->stok_baik + (int) $b->stok_rusak_ringan
             + (int) $b->stok_rusak_berat + $keluar;
    }

    // ===== A. Undo dengan barang hilang =====
    public function test_undo_dengan_hilang_invariant_tetap_jalan(): void
    {
        $b = $this->b(90, 100);
        $a = $this->acara($b, 10);
        $p = $this->kembali($a, $b, ['baik'=>7,'ringan'=>2,'berat'=>0,'hilang'=>1]);

        $b->refresh();
        $this->assertSame(99, (int) $b->stok_total, '1 hilang -> total 99');

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $b->refresh();
        $this->assertSame(90, (int) $b->stok_baik);
        $this->assertSame(0,  (int) $b->stok_rusak_ringan);
        $this->assertSame(100, (int) $b->stok_total, 'hilang dikembalikan -> 100');
        $this->assertSame(100, $this->invariant($b), 'invariant harus 100');
    }

    public function test_undo_kedua_kali_ditolak(): void
    {
        $b = $this->b(90, 100);
        $a = $this->acara($b, 10);
        $p = $this->kembali($a, $b, ['baik'=>10,'ringan'=>0,'berat'=>0,'hilang'=>0]);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();
        $this->assertSame(90, $b->fresh()->stok_baik);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();
        $this->assertSame(90, $b->fresh()->stok_baik, 'undo kedua tidak boleh mengubah stok');
    }

    // ===== B. Acara selesai / batal =====
    public function test_batalkan_acara_selesai_ditolak(): void
    {
        $b = $this->b(90, 100);
        $a = $this->acara($b, 10);
        $this->kembali($a, $b, ['baik'=>10,'ringan'=>0,'berat'=>0,'hilang'=>0]);

        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $this->assertSame('selesai', $a->fresh()->status, 'acara selesai tidak boleh dibatalkan');
        $this->assertSame(100, $b->fresh()->stok_baik, 'stok tidak berubah');
    }

    public function test_edit_acara_selesai_ditolak(): void
    {
        $b = $this->b(90, 100);
        $a = $this->acara($b, 10);
        $this->kembali($a, $b, ['baik'=>10,'ringan'=>0,'berat'=>0,'hilang'=>0]);

        $this->put("/acara/{$a->id}", [
            'nama_acara'=>'Ubah','tanggal'=>'2025-06-10','items'=>[],
        ])->assertRedirect();

        $this->assertSame('Acara', $a->fresh()->nama_acara, 'nama tidak berubah');
    }

    public function test_konfirmasi_reservasi_tidak_bisa_untuk_yang_kurang(): void
    {
        $b = $this->b(5, 5);
        $a = $this->acara($b, 10, 'reservasi');

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $this->assertSame('reservasi', $a->fresh()->status);
        $this->assertSame(5, $b->fresh()->stok_baik, 'stok tidak berubah');
    }

    // ===== C. Kurangi stok saat barang dipesan =====
    public function test_kurangi_stok_tidak_bikin_baik_negatif(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        // 50 sedang dipesan -> sisa bebas 50. Kurangi 100 harus ditolak.
        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 100])->assertRedirect();

        $b->refresh();
        $this->assertSame(100, (int) $b->stok_baik, 'ditolak, stok tidak berubah');

        // kurangi sebatas sisa bebas (50) -> boleh
        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 50])->assertRedirect();

        $b->refresh();
        $this->assertSame(50, (int) $b->stok_baik, '50 bebas -> 50');
        $this->assertSame(0, $this->invariant($b) - 50, 'invariant tetap (50 baik + 50 keluar)');
    }

    // ===== D. Hilang saat barang ada di reservasi =====
    public function test_tandai_hilang_tidak_bikin_total_minus(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 101, 'jenis' => 'hilang',
        ])->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(100, (int) $b->stok_baik, 'ditolak, stok utuh');
    }

    public function test_tandai_hilang_semua_tidak_bikin_total_minus(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 100, 'jenis' => 'hilang',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(0, (int) $b->stok_baik);
        $this->assertSame(0, (int) $b->stok_total);
        $this->assertSame(0, $this->invariant($b), 'total 0 = 0+0+0+0');
    }
}