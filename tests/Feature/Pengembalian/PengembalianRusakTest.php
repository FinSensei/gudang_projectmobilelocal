<?php

namespace Tests\Feature\Pengembalian;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\Pengembalian;
use App\Services\PengembalianService;
use Tests\TestCase;

class PengembalianRusakTest extends TestCase
{
    /**
     * @param  array{bbaik:int,ringan:int,berat:int}  $isi
     */
    private function skenario(int $keluar, array $isi): array
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'PR'.uniqid(),
            'stok_total' => 100, 'stok_baik' => 100 - $keluar,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);

        $a = Acara::create([
            'nama_acara' => 'Acara Rusak', 'tanggal' => '2025-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'Petugas',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => $keluar]);

        $p = Pengembalian::create([
            'tanggal_pulang' => '2025-06-11', 'dicatat_oleh' => 'Petugas', 'catatan' => null,
        ]);
        $p->acara()->attach($a->id);

        $d = DetailPengembalian::create([
            'pengembalian_id' => $p->id, 'barang_id' => $b->id,
            'jumlah_kembali' => $isi['baik'],
            'jumlah_rusak_ringan' => $isi['ringan'],
            'jumlah_rusak_berat' => $isi['berat'],
            'jumlah_hilang' => 0,
        ]);

        app(PengembalianService::class)->hitung($p->fresh());

        return [$b->fresh(), $d->fresh()];
    }

    public function test_rusak_ringan_dan_berat_tersimpan(): void
    {
        [$b, $d] = $this->skenario(10, ['baik' => 7, 'ringan' => 2, 'berat' => 1]);

        $this->assertSame(7, (int) $d->jumlah_kembali, 'baik 7');
        $this->assertSame(2, (int) $d->jumlah_rusak_ringan);
        $this->assertSame(1, (int) $d->jumlah_rusak_berat);
        $this->assertSame(0, (int) $d->jumlah_hilang, '7+2+1 = 10, tidak ada hilang');
    }

    public function test_stok_bertambah_sesuai_jenisnya(): void
    {
        [$b] = $this->skenario(10, ['baik' => 7, 'ringan' => 2, 'berat' => 1]);

        // awal: baik 90, ringan 0, berat 0
        $this->assertSame(97, (int) $b->stok_baik, 'baik 90 + 7');
        $this->assertSame(2, (int) $b->stok_rusak_ringan, 'ringan +2');
        $this->assertSame(1, (int) $b->stok_rusak_berat, 'berat +1');
    }

    public function test_hilang_dihitung_setelah_rusak(): void
    {
        [, $d] = $this->skenario(10, ['baik' => 6, 'ringan' => 2, 'berat' => 1]);

        $this->assertSame(1, (int) $d->jumlah_hilang, '10 - 6 - 2 - 1 = 1 hilang');
    }

    public function test_rusak_tidak_boleh_melebihi_sisa_keluar(): void
    {
        [$b, $d] = $this->skenario(5, ['baik' => 0, 'ringan' => 3, 'berat' => 4]);

        $total = (int) $d->jumlah_kembali + (int) $d->jumlah_rusak_ringan + (int) $d->jumlah_rusak_berat;

        $this->assertLessThanOrEqual(5, $total, 'baik+ringan+berat tidak boleh melebihi keluar');
        $this->assertGreaterThanOrEqual(0, (int) $d->jumlah_hilang, 'hilang tidak boleh negatif');
        $this->assertSame(0, (int) $d->jumlah_hilang, 'semua terpakai, tidak ada hilang');
    }

    public function test_invariant_stok_tetap_setelah_pengembalian(): void
    {
        [$b] = $this->skenario(10, ['baik' => 6, 'ringan' => 2, 'berat' => 1]);

        $inv = $b->stok_baik + $b->stok_rusak_ringan + $b->stok_rusak_berat;

        $this->assertSame(99, (int) $b->stok_total, '1 hilang -> total 99');
        $this->assertSame(99, $inv, 'baik + ringan + berat = total');
    }

    public function test_form_pengembalian_punya_field_ringan_dan_berat(): void
    {
        $b = Barang::create([
            'nama_barang' => 'Sendok', 'satuan' => 'pcs', 'kode_qr' => 'FR1',
            'stok_total' => 100, 'stok_baik' => 90,
            'stok_rusak_ringan' => 0, 'stok_rusak_berat' => 0,
        ]);
        $a = Acara::create([
            'nama_acara' => 'Acara Aktif', 'tanggal' => '2025-06-10', 'status' => 'aktif',
            'penanggung_jawab' => 'Petugas',
        ]);
        DetailAcara::create(['acara_id' => $a->id, 'barang_id' => $b->id, 'jumlah_keluar' => 10]);

        $this->get('/pengembalian')
            ->assertOk()
            ->assertSee('rusak_ringan', false)
            ->assertSee('rusak_berat', false)
            ->assertSee('Rusak Ringan', false)
            ->assertSee('Rusak Berat', false);
    }
}
