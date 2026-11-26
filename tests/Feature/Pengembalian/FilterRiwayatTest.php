<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;
use Tests\TestCase;

class FilterRiwayatTest extends TestCase
{
    private function buat(string $tanggalPulang, string $nama): void
    {
        $b = Barang::create([
            'nama_barang' => $nama, 'satuan' => 'pcs', 'kode_qr' => 'R'.$nama,
            'stok_total' => 100, 'stok_baik' => 100, 'stok_rusak' => 0,
        ]);

        $a = Acara::create([
            'nama_acara' => 'Acara '.$nama, 'tanggal' => '2025-01-01', 'status' => 'selesai',
            'penanggung_jawab' => 'Petugas',
        ]);

        $p = Pengembalian::create([
            'tanggal_pulang' => $tanggalPulang,
            'dicatat_oleh' => 'Petugas',
            'catatan' => null,
        ]);
        $p->acara()->attach($a->id);

        DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_keluar' => 10, 'jumlah_kembali' => 10, 'jumlah_hilang' => 0,
        ]);
    }

    public function test_filter_tanggal_menyaring_hasil(): void
    {
        $this->buat('2025-01-05', 'Jan');
        $this->buat('2025-02-15', 'Feb');

        $res = $this->get('/riwayat?dari=2025-01-01&sampai=2025-01-31')->assertOk();

        $res->assertSee('Acara Jan');
        $res->assertDontSee('Acara Feb', false);
    }

    public function test_tanpa_filter_menampilkan_semua(): void
    {
        $this->buat('2025-01-05', 'Jan');
        $this->buat('2025-02-15', 'Feb');

        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('Acara Jan')
            ->assertSee('Acara Feb');
    }

    public function test_halaman_punya_form_filter_dan_reset(): void
    {
        $this->get('/riwayat')
            ->assertOk()
            ->assertSee('name="dari"', false)
            ->assertSee('name="sampai"', false)
            ->assertSee('Reset', false);
    }
}
