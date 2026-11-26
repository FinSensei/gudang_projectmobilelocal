<?php

namespace App\Services;

use App\Models\Acara;
use App\Models\DetailAcara;
use App\Models\LogAktivitas;
use App\Models\Pengembalian;
use Illuminate\Support\Facades\DB;

class PengembalianService
{
    /**
     * Hitung kehilangan untuk satu gelombang pengembalian.
     *
     * Per barang: keluar = baik + rusak ringan + rusak berat + hilang
     * - baik          -> stok_baik bertambah
     * - rusak ringan  -> stok_rusak_ringan bertambah
     * - rusak berat   -> stok_rusak_berat bertambah
     * - hilang        -> stok_total berkurang
     *
     * Kalau total (baik+ringan+berat) melebihi jumlah keluar, kelebihannya
     * dipotong dari rusak berat dulu, lalu ringan, terakhir baik — supaya
     * laporan tidak pernah mengarang barang.
     */
    public function hitung(Pengembalian $p): array
    {
        $penyesuaian = [];

        DB::transaction(function () use ($p, &$penyesuaian) {
            $p->load(['acara', 'detailPengembalian.barang']);
            $acaraIds = $p->acara->pluck('id')->all();

            // Satu query untuk semua detail_acara (hindari N+1)
            $keluarMap = DetailAcara::whereIn('acara_id', $acaraIds)
                ->selectRaw('barang_id, SUM(jumlah_keluar) as total')
                ->groupBy('barang_id')
                ->pluck('total', 'barang_id')
                ->map(fn ($v) => (int) $v)
                ->all();

            foreach ($p->detailPengembalian as $detail) {
                $qtyOut = (int) ($keluarMap[$detail->barang_id] ?? 0);

                $baik = (int) $detail->jumlah_kembali;
                $ringan = (int) $detail->jumlah_rusak_ringan;
                $berat = (int) $detail->jumlah_rusak_berat;

                // Tidak boleh lebih banyak dari yang keluar
                $inputAwalKembali = $baik;
                $lebih = ($baik + $ringan + $berat) - $qtyOut;
                if ($lebih > 0) {
                    $potong = min($berat, $lebih);
                    $berat -= $potong;
                    $lebih -= $potong;

                    $potong = min($ringan, $lebih);
                    $ringan -= $potong;
                    $lebih -= $potong;

                    $baik = max(0, $baik - $lebih);
                }

                $hilang = $qtyOut - ($baik + $ringan + $berat);

                $disesuaikan = $lebih > 0;

                $detail->jumlah_kembali = $baik;
                $detail->jumlah_rusak_ringan = $ringan;
                $detail->jumlah_rusak_berat = $berat;
                $detail->jumlah_hilang = $hilang;
                $detail->save();

                if ($disesuaikan) {
                    LogAktivitas::create([
                        'aksi' => 'pengembalian_clamp',
                        'entitas' => 'detail_pengembalian',
                        'keterangan' => "detail #{$detail->id}: input disesuaikan agar tidak melebihi jumlah keluar ({$qtyOut})",
                    ]);

                    $penyesuaian[] = [
                        'barang_id' => $detail->barang_id,
                        'nama_barang' => $detail->barang->nama_barang,
                        'input_kembali' => $inputAwalKembali,
                        'dijadikan' => $baik,
                        'qty_out' => $qtyOut,
                    ];
                }

                $barang = $detail->barang; // sudah di-load via eager loading di atas
                $barang->stok_baik += $baik;
                $barang->stok_rusak_ringan += $ringan;
                $barang->stok_rusak_berat += $berat;
                $barang->stok_total -= $hilang;
                $barang->save();
            }

            Acara::whereIn('id', $acaraIds)->update(['status' => 'selesai']);
        });

        return $penyesuaian;
    }
}
