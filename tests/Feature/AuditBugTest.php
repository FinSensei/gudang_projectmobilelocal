<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Models\User;

class AuditBugTest extends TestCase
{
    private function b(int $baik = 90): Barang
    {
        return Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'AB'.uniqid(),
            'stok_total'=>100,'stok_baik'=>$baik,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    /** Bikin acara aktif + pengembalian (dengan rusak), lalu balikkan detailnya. */
    private function acaraDenganPengembalian(Barang $b, int $keluar, array $isi): array
    {
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>$keluar]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2025-06-11','dicatat_oleh'=>'P','catatan'=>null]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id'=>$p->id,'barang_id'=>$b->id,
            'jumlah_kembali'=>$isi['baik'],
            'jumlah_rusak_ringan'=>$isi['ringan'],
            'jumlah_rusak_berat'=>$isi['berat'],
            'jumlah_hilang'=>$isi['hilang'],
        ]);

        app(\App\Services\PengembalianService::class)->hitung($p->fresh());

        return [$a, $p];
    }

    // ================= BUG 1: Undo tidak mengembalikan stok rusak =================
    public function test_undo_pengembalian_mengembalikan_stok_rusak(): void
    {
        $b = $this->b(90);
        [$a, $p] = $this->acaraDenganPengembalian($b, 10, ['baik'=>7,'ringan'=>2,'berat'=>1,'hilang'=>0]);

        // sesudah kembali: baik 97, ringan 2, berat 1
        $b->refresh();
        $this->assertSame(97, (int) $b->stok_baik);
        $this->assertSame(2,  (int) $b->stok_rusak_ringan);
        $this->assertSame(1,  (int) $b->stok_rusak_berat);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $b->refresh();
        $this->assertSame(90, (int) $b->stok_baik, 'baik kembali ke 90');
        $this->assertSame(0,  (int) $b->stok_rusak_ringan, 'rusak ringan harus kembali 0');
        $this->assertSame(0,  (int) $b->stok_rusak_berat, 'rusak berat harus kembali 0');
    }

    public function test_invariant_stok_setelah_undo_tetap_jalan(): void
    {
        $b = $this->b(90);
        [$a, $p] = $this->acaraDenganPengembalian($b, 10, ['baik'=>7,'ringan'=>2,'berat'=>1,'hilang'=>0]);

        $this->post("/pengembalian/{$p->id}/undo")->assertRedirect();

        $b->refresh();

        // acara kembali 'aktif' -> 10 barang masih keluar
        $keluar = (int) DetailAcara::whereHas('acara', fn ($q) => $q->where('status', 'aktif'))
            ->where('barang_id', $b->id)->sum('jumlah_keluar');

        $inv = (int) $b->stok_baik + (int) $b->stok_rusak_ringan
             + (int) $b->stok_rusak_berat + $keluar;

        $this->assertSame((int) $b->stok_total, $inv, 'baik + ringan + berat + keluar harus = total');
    }

    // ================= BUG 2: Batalkan reservasi =================
    public function test_reservasi_bisa_dibatalkan(): void
    {
        $b = $this->b(100);
        $a = Acara::create([
            'nama_acara'=>'Reservasi','tanggal'=>'2025-07-01','status'=>'reservasi','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->post("/acara/{$a->id}/batalkan")->assertRedirect();

        $this->assertSame('batal', $a->fresh()->status, 'reservasi harus bisa dibatalkan');
        $this->assertSame(100, $b->fresh()->stok_baik, 'stok tidak berubah (belum dipotong)');
    }

    // ================= BUG 3: Pekerja buka /login =================
    public function test_pekerja_yang_sudah_login_tidak_stuck_di_403(): void
    {
        $p = User::create([
            'name'=>'Pekerja','email'=>'p@x.id','password'=>bcrypt('x'),'role'=>'pekerja',
        ]);

        $this->actingAs($p);

        $res = $this->get('/login');

        $this->assertNotSame(403, $res->getStatusCode(), 'pekerja tidak boleh dapat 403 di /login');
    }

    // ================= BUG 4: Barang di reservasi tidak bisa dihapus =================
    public function test_barang_yang_sedang_direservasi_tidak_bisa_dihapus(): void
    {
        $b = $this->b(100);
        $a = Acara::create([
            'nama_acara'=>'Reservasi','tanggal'=>'2025-07-01','status'=>'reservasi','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->delete("/barang/{$b->id}")->assertRedirect();

        $this->assertNotNull($b->fresh(), 'barang yang dipesan tidak boleh terhapus');
    }
}