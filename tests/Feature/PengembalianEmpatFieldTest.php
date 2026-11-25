<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;
use App\Services\PengembalianService;

class PengembalianEmpatFieldTest extends TestCase
{
    /**
     * Siapkan acara aktif + barang, lalu POST seperti form pengembalian.
     *
     * @param array{ringan:int,berat:int,hilang:int} $isi
     * @return array{0:Barang,1:DetailPengembalian}
     */
    private function kembali(int $keluar, array $isi): array
    {
        $b = Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'4F'.uniqid(),
            'stok_total'=>100,'stok_baik'=>100-$keluar,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);

        $a = Acara::create([
            'nama_acara'=>'Acara 4F','tanggal'=>'2025-06-10','status'=>'aktif',
            'penanggung_jawab'=>'Petugas',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>$keluar]);

        $this->post('/pengembalian/preview', [
            'acara_ids'      => [$a->id],
            'rusak_ringan'   => [$b->id => $isi['ringan']],
            'rusak_berat'    => [$b->id => $isi['berat']],
            'hilang'         => [$b->id => $isi['hilang']],
        ])->assertSessionHasNoErrors();

        $this->post('/pengembalian', [
            'acara_ids'      => [$a->id],
            'rusak_ringan'   => [$b->id => $isi['ringan']],
            'rusak_berat'    => [$b->id => $isi['berat']],
            'hilang'         => [$b->id => $isi['hilang']],
        ])->assertRedirect();

        $p = Pengembalian::latest('id')->first();
        $d = DetailPengembalian::where('pengembalian_id', $p->id)
            ->where('barang_id', $b->id)->first();

        return [$b->fresh(), $d];
    }

    // ---------- Baik dihitung otomatis ----------
    public function test_baik_otomatis_dari_keluar_ringan_berat_hilang(): void
    {
        [$b, $d] = $this->kembali(100, ['ringan'=>2,'berat'=>3,'hilang'=>1]);

        $this->assertSame(94, (int) $d->jumlah_kembali, '100 - 2 - 3 - 1 = 94 baik');
        $this->assertSame(2,  (int) $d->jumlah_rusak_ringan);
        $this->assertSame(3,  (int) $d->jumlah_rusak_berat);
        $this->assertSame(1,  (int) $d->jumlah_hilang);
    }

    public function test_empat_angka_selalu_pas_dengan_keluar(): void
    {
        [, $d] = $this->kembali(100, ['ringan'=>2,'berat'=>3,'hilang'=>1]);

        $total = (int) $d->jumlah_kembali + (int) $d->jumlah_rusak_ringan
               + (int) $d->jumlah_rusak_berat + (int) $d->jumlah_hilang;

        $this->assertSame(100, $total, 'baik + ringan + berat + hilang = keluar');
    }

    public function test_hilang_besar_tidak_bikin_baik_negatif(): void
    {
        $b = Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'4Z'.uniqid(),
            'stok_total'=>100,'stok_baik'=>90,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        // 1 + 1 + 99 = 101 > 10 -> ditolak, jadi tidak ada angka baik negatif
        $this->post('/pengembalian', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 1],
            'rusak_berat'  => [$b->id => 1],
            'hilang'       => [$b->id => 99],
        ])->assertSessionHasErrors('rusak_ringan');

        $this->assertSame(0, Pengembalian::count(), 'tidak tersimpan');
        $this->assertSame(90, $b->fresh()->stok_baik, 'stok baik tidak berubah');
    }

    // ---------- Validasi ----------
    public function test_total_ringan_berat_hilang_tidak_boleh_melebihi_keluar(): void
    {
        $b = Barang::create([
            'nama_barang'=>'Gelas','satuan'=>'pcs','kode_qr'=>'4G'.uniqid(),
            'stok_total'=>100,'stok_baik'=>90,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        // 6 + 6 + 0 = 12 > 10
        $this->post('/pengembalian', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 6],
            'rusak_berat'  => [$b->id => 6],
            'hilang'       => [$b->id => 0],
        ])->assertSessionHasErrors('rusak_ringan');

        $this->assertSame(0, Pengembalian::count(), 'tidak boleh tersimpan');
    }

    // ---------- Form punya field hilang ----------
    public function test_form_pengembalian_punya_field_hilang(): void
    {
        $b = Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'4H'.uniqid(),
            'stok_total'=>100,'stok_baik'=>90,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>100]);

        $this->get('/pengembalian')
            ->assertOk()
            ->assertSee('name="hilang[', false)
            ->assertSee('name="rusak_ringan[', false)
            ->assertSee('name="rusak_berat[', false);
    }

    // ---------- Review ikut menampilkan hilang ----------
    public function test_review_menampilkan_kolom_hilang(): void
    {
        $b = Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'4I'.uniqid(),
            'stok_total'=>100,'stok_baik'=>90,
            'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
        $a = Acara::create([
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','status'=>'aktif','penanggung_jawab'=>'P',
        ]);
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $this->post('/pengembalian/preview', [
            'acara_ids'    => [$a->id],
            'rusak_ringan' => [$b->id => 2],
            'rusak_berat'  => [$b->id => 3],
            'hilang'       => [$b->id => 1],
        ])
            ->assertOk()
            ->assertSee('Baik', false)
            ->assertSee('Ringan', false)
            ->assertSee('Berat', false)
            ->assertSee('Hilang', false);
    }
}