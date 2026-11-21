<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Barang extends Model {
    use SoftDeletes;
    protected $table='barang';
    protected $fillable=['kategori_id','nama_barang','satuan','kode_qr','stok_total','stok_baik','stok_rusak','lokasi','foto','status_kondisi'];
    protected function casts(): array { return ['stok_total'=>'integer','stok_baik'=>'integer','stok_rusak'=>'integer']; }
    public function kategori(): BelongsTo { return $this->belongsTo(Kategori::class,'kategori_id'); }
    public function detailAcara(): HasMany { return $this->hasMany(DetailAcara::class,'barang_id'); }
    public function detailPengembalian(): HasMany { return $this->hasMany(DetailPengembalian::class,'barang_id'); }
    // Jumlah unit yang sedang keluar ke acara aktif (diturunkan, tidak disimpan)
    public function stokKeluar(): int {
        return (int) DetailAcara::whereIn('acara_id', Acara::where('status','aktif')->pluck('id'))
            ->where('barang_id',$this->id)->sum('jumlah_keluar');
    }
    public function statusPemakaian(): string { return $this->stokKeluar()>0 ? 'sedang digunakan' : 'tersedia'; }
}