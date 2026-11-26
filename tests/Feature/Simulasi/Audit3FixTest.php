<?php

namespace Tests\Feature\Simulasi;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Tests\TestCase;

class Audit3FixTest extends TestCase
{
    private function b(int $baik = 100, int $total = 100): Barang
    {
        return Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'A3'.uniqid(),
            'stok_total' => $total, 'stok_baik' => $baik,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
    }

    private function acara(Barang $b, int $jumlah, string $status): Acara
    {
        $a = Acara::create([
            'nama_acara' => 'Acara', 'tanggal' => '2025-06-10', 'status' => $status, 'penanggung_jawab' => 'P',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => $jumlah]);

        return $a;
    }

    private function pesan(Acara $a): int
    {
        return (int) DetailAcara::where('acara_id', $a->id)
            ->whereHas('acara', fn ($q) => $q->whereIn('status', ['reservasi', 'aktif']))
            ->sum('jumlah_keluar');
    }

    // ===== P1a: lock di konfirmasi (cek SQL) =====
    public function test_konfirmasi_memakai_row_lock(): void
    {
        $b = $this->b(100, 100);
        $a = $this->acara($b, 10, 'reservasi');

        $sql = [];
        \DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });

        $this->post("/acara/{$a->id}/konfirmasi")->assertRedirect();

        $ada = collect($sql)->contains(fn ($s) => str_contains($s, 'for update'));
        $this->assertTrue($ada, 'konfirmasi harus lock baris (SELECT ... FOR UPDATE)');
    }

    // ===== P1b: cegah hilang saat barang dipesan =====
    public function test_tandai_hilang_ditolak_kalau_ada_reservasi(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 10, 'jenis' => 'hilang',
        ])->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(100, (int) $b->stok_baik);
        $this->assertSame(100, (int) $b->stok_total);
    }

    public function test_tandai_rusak_boleh_kalau_ada_reservasi(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        // rusak 10 -> masih cukup untuk memenuhi reservasi 50
        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 10, 'jenis' => 'rusak', 'tingkat' => 'ringan',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(90, (int) $b->stok_baik);
    }

    public function test_hilang_boleh_kalau_tidak_ada_reservasi(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id, 'jumlah' => 10, 'jenis' => 'hilang',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(90, (int) $b->stok_baik);
    }

    // ===== P2a: kurangi stok tidak boleh merusak reservasi =====
    public function test_kurangi_stok_ditolak_kalau_bikin_reservasi_tak_terpenuhi(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        // baik 100, dipesan 50 -> sisa bebas 50. Kurangi 80 -> sisa 20 < 50
        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 80])
            ->assertRedirect()
            ->assertSessionHas('err');

        $b->refresh();
        $this->assertSame(100, (int) $b->stok_baik);
    }

    public function test_kurangi_stok_sebatas_sisa_bebas_boleh(): void
    {
        $b = $this->b(100, 100);
        $this->acara($b, 50, 'reservasi');

        $this->post("/barang/{$b->id}/kurangi", ['jumlah' => 50])->assertRedirect();

        $b->refresh();
        $this->assertSame(50, (int) $b->stok_baik, 'pas tersisa 50 untuk reservasi');
    }
}
