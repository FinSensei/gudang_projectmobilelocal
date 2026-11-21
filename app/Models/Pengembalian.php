<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Pengembalian extends Model {
    protected $table='pengembalian';
    protected $fillable=['tanggal_pulang','dicatat_oleh','catatan'];
    protected function casts(): array { return ['tanggal_pulang'=>'date']; }
    public function acara(): BelongsToMany { return $this->belongsToMany(Acara::class,'acara_pengembalian','pengembalian_id','acara_id'); }
    public function detailPengembalian(): HasMany { return $this->hasMany(DetailPengembalian::class,'pengembalian_id'); }
}