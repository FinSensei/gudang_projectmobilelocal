<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;
use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\Pengembalian;
use App\Models\DetailPengembalian;

class EditAcaraTest extends TestCase
{
    private function b(string $kode, int $baik = 100): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok '.$kode,'satuan'=>'pcs','kode_qr'=>'EA'.$kode,
            'stok_total'=>100,'stok_baik'=>100,'stok_rusak_ringan'=>0,'stok_rusak_berat'=>0,
        ]);
    }

    private function acara(string $status = 'aktif'): Acara
    {
        return Acara::create([
            'nama_acara'=>'Acara Awal','tanggal'=>'2025-06-10','status'=>$status,
            'penanggung_jawab'=>'Petugas',
        ]);
    }

    public function test_form_edit_menampilkan_data_acara(): void
    {
        $a = $this->acara();

        $this->get("/acara/{$a->id}/edit")
            ->assertOk()
            ->assertSee('Acara Awal', false)
            ->assertSee('name="nama_acara"', false);
    }

    public function test_edit_nama_dan_penanggung_jawab(): void
    {
        $a = $this->acara();

        $this->put("/acara/{$a->id}", [
            'nama_acara'       => 'Nama Baru',
            'tanggal'          => '2025-07-01',
            'penanggung_jawab' => 'Budi',
            'items'            => [],
        ])->assertRedirect();

        $a->refresh();
        $this->assertSame('Nama Baru', $a->nama_acara);
        $this->assertSame('Budi', $a->penanggung_jawab);
    }

    public function test_tambah_barang_memotong_stok_baik(): void
    {
        $b = $this->b('1');
        $a = $this->acara('aktif');

        $this->put("/acara/{$a->id}", [
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','items'=>[
                ['barang_id'=>$b->id,'jumlah'=>20],
            ],
        ])->assertRedirect();

        $this->assertSame(80, $b->fresh()->stok_baik, '100 - 20 = 80');
    }

    public function test_kurangi_barang_menambah_stok_baik(): void
    {
        $b = $this->b('1');
        $a = $this->acara('aktif');
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>20]);
        $b->stok_baik = 80; $b->save();

        // kurangi jadi 5 -> 15 kembali ke stok
        $this->put("/acara/{$a->id}", [
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','items'=>[
                ['barang_id'=>$b->id,'jumlah'=>5],
            ],
        ])->assertRedirect();

        $this->assertSame(95, $b->fresh()->stok_baik, '80 + 15 = 95');
    }

    public function test_hapus_barang_dari_acara_mengembalikan_stok(): void
    {
        $b = $this->b('1');
        $a = $this->acara('aktif');
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>20]);
        $b->stok_baik = 80; $b->save();

        // barang dikeluarkan dari daftar
        $this->put("/acara/{$a->id}", [
            'nama_acara'=>'Acara','tanggal'=>'2025-06-10','items'=>[],
        ])->assertRedirect();

        $this->assertSame(100, $b->fresh()->stok_baik, '80 + 20 = 100');
        $this->assertSame(0, DetailAcara::where('acara_id',$a->id)->count());
    }

    public function test_edit_dikunci_jika_sudah_ada_pengembalian(): void
    {
        $b = $this->b('1');
        $a = $this->acara('selesai');
        DetailAcara::create(['acara_id'=>$a->id,'barang_id'=>$b->id,'jumlah_keluar'=>10]);

        $p = Pengembalian::create(['tanggal_pulang'=>'2025-06-11','dicatat_oleh'=>'Petugas','catatan'=>null]);
        $p->acara()->attach($a->id);
        DetailPengembalian::create([
            'pengembalian_id'=>$p->id,'barang_id'=>$b->id,
            'jumlah_kembali'=>10,'jumlah_rusak_ringan'=>0,'jumlah_rusak_berat'=>0,'jumlah_hilang'=>0,
        ]);

        $this->put("/acara/{$a->id}", [
            'nama_acara'=>'Coba Ubah','tanggal'=>'2025-06-10','items'=>[],
        ])->assertRedirect();

        $this->assertSame('Acara Awal', $a->fresh()->nama_acara, 'nama tidak berubah');
    }
}