<?php

namespace App\Services;

use App\Models\DetailAcara;

class StokService
{
    /**
     * Jumlah barang yang sedang dipesan (reservasi) atau keluar (aktif).
     * Dipakai supaya stok baik tidak dirombak sampai reservasi tak terpenuhi.
     */
    public function jumlahDipesan(int $barangId): int
    {
        return (int) DetailAcara::where('barang_id', $barangId)
            ->whereHas('acara', fn ($q) => $q->whereIn('status', ['reservasi', 'aktif']))
            ->sum('jumlah_keluar');
    }
}
