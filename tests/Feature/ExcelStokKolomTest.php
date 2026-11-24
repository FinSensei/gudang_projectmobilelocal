<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;

class ExcelStokKolomTest extends TestCase
{
    private function loadRows(): array
    {
        $xlsx = app(\App\Services\ExcelExportService::class)->stok();
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $xlsx->save($path);
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        return $rows;
    }

    protected function seedBarang(): Barang
    {
        return Barang::create([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'S1',
            'stok_total'=>100,'stok_baik'=>90,'stok_rusak'=>10,'ukuran'=>'21 cm',
        ]);
    }

    public function test_kolom_total_stok_baik_rusak_ada(): void
    {
        $this->seedBarang();
        $rows = $this->loadRows();

        // header di baris 3
        $header = array_map(fn ($v) => (string) $v, $rows[2]);

        $this->assertSame('No',          $header[0]);
        $this->assertSame('Kode Barang', $header[1]);
        $this->assertSame('Nama Barang', $header[2]);
        $this->assertSame('Photo',       $header[3]);
        $this->assertSame('Total Stok',  $header[4]);
        $this->assertSame('Stok Baik',   $header[5]);
        $this->assertSame('Stok Rusak',  $header[6]);
    }

    public function test_total_stok_adalah_baik_plus_rusak(): void
    {
        $this->seedBarang();
        $rows = $this->loadRows();

        $data = $rows[3];
        // Total Stok = baik + rusak = 90 + 10 = 100
        $this->assertSame(100, (int) $data[4], 'Total Stok = baik + rusak');
        $this->assertSame(90,  (int) $data[5], 'Stok Baik');
        $this->assertSame(10,  (int) $data[6], 'Stok Rusak');
    }

    public function test_total_stok_tidak_menghitung_yang_keluar(): void
    {
        // baik 50, rusak 10, 40 sedang di event -> total 60, bukan 100
        Barang::create([
            'nama_barang'=>'Garpu','satuan'=>'pcs','kode_qr'=>'S2',
            'stok_total'=>100,'stok_baik'=>50,'stok_rusak'=>10,
        ]);

        $rows = $this->loadRows();
        $data = null;
        foreach (array_slice($rows, 3) as $r) {
            if (($r[2] ?? '') === 'Garpu') { $data = $r; break; }
        }
        $this->assertNotNull($data);
        $this->assertSame(60, (int) $data[4], 'Total Stok = 50 + 10 = 60');
    }
}