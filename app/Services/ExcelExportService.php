<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\Pengembalian;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ExcelExportService
{
    private function base(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        return $ss;
    }

    private function styleHeader(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F6FEB');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Rekap pengembalian per gelombang */
    public function riwayat(): Xlsx
    {
        $ss = $this->base();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Rekap Pengembalian');

        $sheet->fromArray(['Tanggal', 'Acara', 'Barang', 'Keluar', 'Kembali', 'Hilang', 'Tersedia'], null, 'A1');
        $this->styleHeader($sheet, 'A1:G1');

        $row = 2;
        $pengembalian = Pengembalian::with(['acara', 'detailPengembalian.barang'])->orderByDesc('id')->get();
        foreach ($pengembalian as $p) {
            $acaraNama = $p->acara->pluck('nama_acara')->join(', ');
            foreach ($p->detailRows() as $r) {
                $sheet->fromArray([
                    $p->tanggal_pulang?->format('d/m/Y') ?: '-',
                    $acaraNama,
                    $r['barang'],
                    $r['keluar'],
                    $r['kembali'],
                    $r['hilang'],
                    $r['tersedia'],
                ], null, 'A'.$row);
                $row++;
            }
        }

        for ($c = 'A'; $c <= 'G'; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return new Xlsx($ss);
    }

    /** Stok barang saat ini */
    public function stok(): Xlsx
    {
        $ss = $this->base();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Stok Barang');

        $sheet->fromArray(['Nama Barang', 'Ukuran', 'Satuan', 'Kode QR', 'Total', 'Baik', 'Rusak', 'Keluar', 'Status Stok', 'Lokasi'], null, 'A1');
        $this->styleHeader($sheet, 'A1:J1');

        $row = 2;
        Barang::orderBy('nama_barang')->get()->each(function ($b) use ($sheet, &$row) {
            $sheet->fromArray([
                $b->nama_barang,
                $b->ukuran ?: '-',
                $b->satuan,
                $b->kode_qr,
                $b->stok_total,
                $b->stok_baik,
                $b->stok_rusak,
                $b->stokKeluar(),
                $b->statusStok(),
                $b->lokasi ?: '-',
            ], null, 'A'.$row);
            $row++;
        });

        for ($c = 'A'; $c <= 'J'; $c++) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        return new Xlsx($ss);
    }
}