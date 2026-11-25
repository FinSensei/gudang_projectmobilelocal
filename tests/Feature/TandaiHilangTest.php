<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;

class TandaiHilangTest extends TestCase
{
    private function b(int $baik = 100, int $total = 100): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'TH'.uniqid(),
            'stok_total'=>$total,'stok_baik'=>$baik,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    // ---------- Form ----------
    public function test_form_punya_pilihan_jenis_rusak_atau_hilang(): void
    {
        $this->b();

        $this->get('/restock')
            ->assertOk()
            ->assertSee('Tandai rusak atau hilang', false)
            ->assertSee('name="jenis"', false)
            ->assertSee('Hilang', false);
    }

    // ---------- Hilang ----------
    public function test_tandai_hilang_mengurangi_baik_dan_total(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'hilang',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(95, (int) $b->stok_baik, 'baik 100 - 5 = 95');
        $this->assertSame(95, (int) $b->stok_total, 'total ikut turun jadi 95');
    }

    public function test_tandai_hilang_tidak_masuk_kolom_rusak(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'hilang',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(0, (int) $b->stok_rusak_ringan, 'ringan tetap 0');
        $this->assertSame(0, (int) $b->stok_rusak_berat, 'berat tetap 0');
    }

    public function test_invariant_setelah_hilang_tetap_jalan(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'hilang',
        ])->assertRedirect();

        $b->refresh();
        $inv = (int) $b->stok_baik + (int) $b->stok_rusak_ringan + (int) $b->stok_rusak_berat;

        $this->assertSame((int) $b->stok_total, $inv, 'baik + ringan + berat = total');
    }

    public function test_hilang_tidak_boleh_melebihi_stok_baik(): void
    {
        $b = $this->b(3, 3);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 10,
            'jenis'     => 'hilang',
        ])->assertSessionHasErrors('jumlah');

        $b->refresh();
        $this->assertSame(3, (int) $b->stok_baik, 'stok tidak berubah');
    }

    // ---------- Rusak (regresi) ----------
    public function test_tandai_rusak_ringan_tetap_jalan(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'rusak',
            'tingkat'   => 'ringan',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(95, (int) $b->stok_baik);
        $this->assertSame(5,  (int) $b->stok_rusak_ringan);
        $this->assertSame(100, (int) $b->stok_total, 'total tetap (barang masih ada, cuma rusak)');
    }

    public function test_tandai_rusak_berat_tetap_jalan(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'rusak',
            'tingkat'   => 'berat',
        ])->assertRedirect();

        $b->refresh();
        $this->assertSame(95, (int) $b->stok_baik);
        $this->assertSame(5,  (int) $b->stok_rusak_berat);
    }

    public function test_rusak_tanpa_tingkat_ditolak(): void
    {
        $b = $this->b(100, 100);

        $this->post('/rusak', [
            'barang_id' => $b->id,
            'jumlah'    => 5,
            'jenis'     => 'rusak',
        ])->assertSessionHasErrors('tingkat');

        $this->assertSame(100, $b->fresh()->stok_baik);
    }
}