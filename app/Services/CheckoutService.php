<?php

namespace App\Services;

use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    /**
     * Reservasi: catat barang yang dipesan untuk acara.
     * TIDAK memotong stok — stok baru dipotong saat dikonfirmasi.
     */
    public function reservasi(Acara $acara, array $items): void
    {
        DB::transaction(function () use ($acara, $items) {
            foreach ($items as $it) {
                $b = Barang::findOrFail($it['barang_id']);   // pastikan barang ada

                // Satu reservasi tidak boleh melebihi stok baik yang ada.
                // Dua reservasi berbeda tetap boleh bersaing (stok belum dipotong).
                if ((int) $it['jumlah'] > (int) $b->stok_baik) {
                    throw new \RuntimeException(
                        "Jumlah {$b->nama_barang} melebihi stok baik (baik {$b->stok_baik}, diminta {$it['jumlah']})."
                    );
                }

                DetailAcara::create([
                    'acara_id'     => $acara->id,
                    'barang_id'    => $it['barang_id'],
                    'jumlah_keluar' => $it['jumlah'],
                ]);
            }
        });
    }

    /**
     * Konfirmasi reservasi: barang benar-benar keluar dari gudang.
     * Mengurangi stok_baik tiap barang, menolak kalau tidak cukup (rollback).
     * Status acara jadi 'aktif' hanya kalau semua stok cukup.
     */
    public function konfirmasi(Acara $acara): void
    {
        DB::transaction(function () use ($acara) {
            if ($acara->status !== 'reservasi') {
                throw new \RuntimeException('Acara tidak berstatus reservasi.');
            }

            foreach ($acara->detailAcara as $d) {
                $b = Barang::findOrFail($d->barang_id);
                if ($b->stok_baik < $d->jumlah_keluar) {
                    throw new \RuntimeException(
                        "Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}, butuh {$d->jumlah_keluar})."
                    );
                }
                $b->stok_baik -= $d->jumlah_keluar;
                $b->save();
            }

            $acara->status = 'aktif';
            $acara->save();
        });
    }

    /**
     * Catat barang keluar untuk satu acara (langsung, tanpa reservasi).
     * Mengurangi stok_baik tiap barang, menolak jika stok tidak cukup (rollback).
     */
    public function buat(Acara $acara, array $items): void
    {
        DB::transaction(function () use ($acara, $items) {
            foreach ($items as $it) {
                $b = Barang::findOrFail($it['barang_id']);
                if ($b->stok_baik < $it['jumlah']) {
                    throw new \RuntimeException("Stok {$b->nama_barang} tidak cukup (baik {$b->stok_baik}).");
                }
                $b->stok_baik -= $it['jumlah'];
                $b->save();
                DetailAcara::create([
                    'acara_id'    => $acara->id,
                    'barang_id'   => $b->id,
                    'jumlah_keluar' => $it['jumlah'],
                ]);
            }
        });
    }
}