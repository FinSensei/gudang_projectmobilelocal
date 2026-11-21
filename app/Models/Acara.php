<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Acara extends Model {
    use SoftDeletes;
    protected $table='acara';
    protected $fillable=['nama_acara','penanggung_jawab','tanggal','lokasi','status','catatan'];
    protected function casts(): array { return ['tanggal'=>'date']; }
    public function detailAcara(): HasMany { return $this->hasMany(DetailAcara::class,'acara_id'); }
    public function pengembalian(): BelongsToMany { return $this->belongsToMany(Pengembalian::class,'acara_pengembalian','acara_id','pengembalian_id'); }
}