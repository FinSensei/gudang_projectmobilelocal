<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Barang;

class ExcelStokRusakTest extends TestCase
{
    private function rows(): array
    {
        $xlsx = app(\App\Services\ExcelExportService::class)->stok();
        $path = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        $xlsx->save($path);
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        @unlink($path);
        return $rows;
    }

    private function barang(array $attr): Barang
    {
        return Barang::create(array_merge([
            'nama_barang'=>'Sendok','satuan'=>'pcs','kode_qr'=>'ER1',
            'stok_total'=>100,'stok_baik'=>90,
        ], $attr));
    }

    public function test_urutan_kolom_sesuai_pilihan_a(): void
    {
        $this->barang([]);

        $header = array_map(fn ($v) => (string) $v, $this->rows()[2]);

        $this->assertSame('No',           $header[0]);
        $this->assertSame('Kode Barang',  $header[1]);
        $this->assertSame('Nama Barang',  $header[2]);
        $this->assertSame('Photo',        $header[3]);
        $this->assertSame('Total Stok',   $header[4]);
        $this->assertSame('Stok Baik',    $header[5]);
        $this->assertSame('Rusak Ringan', $header[6]);
        $this->assertSame('Rusak Berat',  $header[7]);
        $this->assertSame('Keterangan',   $header[8]);
    }

    public function test_total_stok_adalah_baik_plus_semua_rusak(): void
    {
        $this->barang([
            'stok_total'=>100,'stok_baik'=>80,
            'stok_rusak_ringan'=>12,'stok_rusak_berat'=>8,
        ]);

        $data = $this->rows()[3];

        $this->assertSame(100, (int) $data[4], 'Total Stok = baik + ringan + berat');
        $this->assertSame(80,  (int) $data[5]);
        $this->assertSame(12,  (int) $data[6]);
        $this->assertSame(8,   (int) $data[7]);
    }

    public function test_kolom_keterangan_berisi_catatan_opsional(): void
    {
        // baik 5 dari total 100 -> statusStok() = 'menipis'
        $this->barang(['stok_total'=>100,'stok_baik'=>5]);

        $data = $this->rows()[3];
        $ket = (string) $data[8];

        $this->assertNotSame('', $ket, 'kolom Keterangan terisi');
    }

    public function test_keterangan_kosong_untuk_barang_aman(): void
    {
        $this->barang([
            'stok_total'=>100,'stok_baik'=>100,
        ]);

        $data = $this->rows()[3];
        $this->assertSame('', (string) $data[8], 'barang aman tanpa catatan');
    }
}