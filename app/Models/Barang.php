<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barang extends Model
{
    use SoftDeletes;

    protected $table = 'barang';

    protected $fillable = [
        'kategori_id', 'nama_barang', 'satuan', 'ukuran', 'kode_qr',
        'stok_total', 'stok_baik', 'stok_rusak_ringan', 'stok_rusak_berat',
        'lokasi', 'foto', 'status_kondisi',
    ];

    /** Nilai default saat create tanpa menyertakan ukuran */
    protected $attributes = [
        'ukuran' => 'N/A',
        'stok_rusak_ringan' => 0,
        'stok_rusak_berat' => 0,
    ];

    protected function casts(): array
    {
        return [
            'stok_total' => 'integer',
            'stok_baik' => 'integer',
            'stok_rusak_ringan' => 'integer',
            'stok_rusak_berat' => 'integer',
        ];
    }

    /**
     * Total rusak = ringan + berat (turunan, tidak disimpan di DB).
     * Dipakai view & Excel supaya tidak perlu tahu rinciannya.
     */
    public function getStokRusakAttribute(): int
    {
        return (int) $this->stok_rusak_ringan + (int) $this->stok_rusak_berat;
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategori_id');
    }

    public function detailAcara(): HasMany
    {
        return $this->hasMany(DetailAcara::class, 'barang_id');
    }

    public function detailPengembalian(): HasMany
    {
        return $this->hasMany(DetailPengembalian::class, 'barang_id');
    }

    // Jumlah unit yang sedang keluar ke acara aktif (diturunkan, tidak disimpan)
    public function stokKeluar(): int
    {
        return (int) DetailAcara::whereIn('acara_id', Acara::where('status', 'aktif')->pluck('id'))
            ->where('barang_id', $this->id)->sum('jumlah_keluar');
    }

    public function statusPemakaian(): string
    {
        return $this->stokKeluar() > 0 ? 'sedang digunakan' : 'tersedia';
    }

    // Status stok: habis (baik 0) | menipis (baik < 10% dari total) | aman
    public function statusStok(): string
    {
        if ($this->stok_baik <= 0) {
            return 'habis';
        }
        if ($this->stok_total > 0 && $this->stok_baik < $this->stok_total * 0.1) {
            return 'menipis';
        }

        return 'aman';
    }
}
