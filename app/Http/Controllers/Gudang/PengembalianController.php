<?php

namespace App\Http\Controllers\Gudang;

use App\Http\Controllers\Controller;
use App\Models\Acara;
use App\Models\Barang;
use App\Models\DetailAcara;
use App\Models\DetailPengembalian;
use App\Models\LogAktivitas;
use App\Models\Pengembalian;
use App\Services\PengembalianService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PengembalianController extends Controller
{
    public function form()
    {
        $aktif = Acara::where('status', 'aktif')->with('detailAcara.barang')->orderByDesc('id')->get();

        return view('gudang.pengembalian', compact('aktif'));
    }

    /** Ringkasan hitungan pengembalian — dipakai preview & simpan. */
    private function ringkas(array $data): array
    {
        $outMap = [];
        DetailAcara::whereIn('acara_id', $data['acara_ids'])->get()->each(function ($d) use (&$outMap) {
            $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
        });

        // Ringan + berat + hilang tidak boleh melebihi jumlah keluar
        $lebih = [];
        foreach ($outMap as $bid => $out) {
            $total = (int) ($data['rusak_ringan'][$bid] ?? 0)
                   + (int) ($data['rusak_berat'][$bid] ?? 0)
                   + (int) ($data['hilang'][$bid] ?? 0);
            if ($total > $out) {
                $lebih[] = Barang::find($bid)->nama_barang." (isi {$total}, keluar {$out})";
            }
        }
        if ($lebih) {
            return ['error' => 'Ringan + berat + hilang melebihi jumlah keluar: '.implode(', ', $lebih)];
        }

        $rows = [];
        foreach ($outMap as $bid => $out) {
            $b = Barang::find($bid);
            $ringan = (int) ($data['rusak_ringan'][$bid] ?? 0);
            $berat = (int) ($data['rusak_berat'][$bid] ?? 0);
            $hilang = (int) ($data['hilang'][$bid] ?? 0);

            // Baik dihitung otomatis = keluar - ringan - berat - hilang
            $baik = max(0, $out - $ringan - $berat - $hilang);

            $rows[] = [
                'barang_id' => $bid,
                'nama' => $b->nama_barang,
                'keluar' => $out,
                'kembali' => $baik,
                'ringan' => $ringan,
                'berat' => $berat,
                'hilang' => $hilang,
                'keterangan' => ($ket = trim((string) ($data['keterangan'][$bid] ?? ''))) !== '' ? $ket : null,
            ];
        }

        return ['rows' => $rows, 'outMap' => $outMap];
    }

    /** Aturan validasi bersama preview & simpan. */
    private function aturan(): array
    {
        return [
            'acara_ids' => 'required|array|min:1',
            'acara_ids.*' => 'exists:acara,id',
            'kembali' => 'nullable|array',   // baik dihitung otomatis
            'kembali.*' => 'integer|min:0',
            'rusak_ringan' => 'nullable|array',
            'rusak_ringan.*' => 'integer|min:0',
            'rusak_berat' => 'nullable|array',
            'rusak_berat.*' => 'integer|min:0',
            'hilang' => 'nullable|array',
            'hilang.*' => 'integer|min:0',
            'keterangan' => 'nullable|array',
            'keterangan.*' => 'nullable|string|max:255',
        ];
    }

    // preview (langkah 1): hitung dulu, belum simpan
    public function preview(Request $request)
    {
        $data = $request->validate($this->aturan());

        // Acara yang sudah selesai tidak boleh diproses ulang
        $selesai = Acara::whereIn('id', $data['acara_ids'])->where('status', '!=', 'aktif')->exists();
        if ($selesai) {
            return back()->withErrors(['acara_ids' => 'Ada acara yang sudah selesai. Pilih hanya acara yang masih aktif.'])->withInput();
        }

        $ringkas = $this->ringkas($data);
        if (isset($ringkas['error'])) {
            return back()->withErrors(['rusak_ringan' => $ringkas['error']])->withInput();
        }

        $rows = $ringkas['rows'];
        $totalHilang = array_sum(array_column($rows, 'hilang'));

        return view('gudang.pengembalian_review', [
            'acara_ids' => $data['acara_ids'],
            'rows' => $rows,
            'totalHilang' => $totalHilang,
            'tanggal' => $data['tanggal'] ?? now()->toDateString(),
        ]);
    }

    // simpan (langkah 2): panggil service
    public function store(Request $request)
    {
        $data = $request->validate($this->aturan());

        // Acara yang sudah selesai tidak boleh diproses ulang
        $selesai = Acara::whereIn('id', $data['acara_ids'])->where('status', '!=', 'aktif')->exists();
        if ($selesai) {
            return back()->withErrors(['acara_ids' => 'Ada acara yang sudah selesai. Pilih hanya acara yang masih aktif.'])->withInput();
        }

        // Ringan + berat + hilang tidak boleh melebihi jumlah keluar
        $ringkas = $this->ringkas($data);
        if (isset($ringkas['error'])) {
            return back()->withErrors(['rusak_ringan' => $ringkas['error']])->withInput();
        }

        try {
            DB::transaction(function () use ($data) {
                // Kunci baris acara: dua operator tidak bisa memproses acara yang sama
                $terkunci = Acara::whereIn('id', $data['acara_ids'])->lockForUpdate()->get();

                if ($terkunci->count() !== count($data['acara_ids'])) {
                    throw new \RuntimeException('Ada acara yang tidak ditemukan.');
                }
                if ($terkunci->contains(fn ($a) => $a->status !== 'aktif')) {
                    throw new \RuntimeException('Ada acara yang sudah selesai. Pilih hanya acara yang masih aktif.');
                }

                $p = Pengembalian::create([
                    'tanggal_pulang' => $data['tanggal'] ?? now()->toDateString(),
                    'dicatat_oleh' => 'operator',
                ]);
                $p->acara()->attach($data['acara_ids']);

                $outMap = [];
                DetailAcara::whereIn('acara_id', $data['acara_ids'])->get()->each(function ($d) use (&$outMap) {
                    $outMap[$d->barang_id] = ($outMap[$d->barang_id] ?? 0) + $d->jumlah_keluar;
                });
                foreach ($outMap as $bid => $out) {
                    DetailPengembalian::create([
                        'pengembalian_id' => $p->id,
                        'barang_id' => $bid,
                        'jumlah_kembali' => max(0, $out - (int) ($data['rusak_ringan'][$bid] ?? 0) - (int) ($data['rusak_berat'][$bid] ?? 0) - (int) ($data['hilang'][$bid] ?? 0)),
                        'jumlah_rusak_ringan' => (int) ($data['rusak_ringan'][$bid] ?? 0),
                        'jumlah_rusak_berat' => (int) ($data['rusak_berat'][$bid] ?? 0),
                        'jumlah_hilang' => (int) ($data['hilang'][$bid] ?? 0),
                        'keterangan' => (($ket = trim((string) ($data['keterangan'][$bid] ?? ''))) !== '' ? $ket : null),
                    ]);
                }

                $penyesuaian = app(PengembalianService::class)->hitung($p);
                LogAktivitas::create(['aksi' => 'pengembalian', 'entitas' => 'pengembalian', 'keterangan' => 'batch #'.$p->id]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['acara_ids' => $e->getMessage()])->withInput();
        }

        // Tampilkan peringatan di layar kalau ada input yang disesuaikan otomatis
        $pesanOk = 'Pengembalian disimpan, stok diperbarui.';
        if (!empty($penyesuaian)) {
            $daftar = [];
            foreach ($penyesuaian as $pen) {
                $daftar[] = "{$pen['nama_barang']}: Anda isi kembali {$pen['input_kembali']}, tapi yang keluar cuma {$pen['qty_out']}. Kami ubah jadi {$pen['dijadikan']}.";
            }
            $pesanOk .= ' Perhatian: ' . implode(' ', $daftar);
        }

        return redirect('/')->with('ok', $pesanOk);
    }

    /** Undo pengembalian: kembalikan stok & status acara ke aktif */
    public function undo($id)
    {
        $p = Pengembalian::withTrashed()->with(['acara', 'detailPengembalian'])->findOrFail($id);

        if ($p->trashed()) {
            return back()->with('err', 'Pengembalian ini sudah dibatalkan.');
        }

        DB::transaction(function () use ($p) {
            $acaraIds = $p->acara->pluck('id')->all();

            foreach ($p->detailPengembalian as $detail) {
                $b = Barang::find($detail->barang_id);
                if ($b) {
                    // Batalkan efek: barang yang tadinya masuk harus dikeluarkan lagi
                    $b->stok_baik = max(0, (int) $b->stok_baik - (int) $detail->jumlah_kembali);
                    $b->stok_rusak_ringan = max(0, (int) $b->stok_rusak_ringan - (int) $detail->jumlah_rusak_ringan);
                    $b->stok_rusak_berat = max(0, (int) $b->stok_rusak_berat - (int) $detail->jumlah_rusak_berat);
                    // barang hilang kembali dianggap ada
                    $b->stok_total += (int) $detail->jumlah_hilang;
                    $b->save();
                }
            }

            Acara::whereIn('id', $acaraIds)->update(['status' => 'aktif']);
            $p->delete(); // soft delete
            LogAktivitas::create(['aksi' => 'undo_pengembalian', 'entitas' => 'pengembalian', 'keterangan' => 'batch #'.$p->id]);
        });

        return back()->with('ok', "Pengembalian batch #{$p->id} dibatalkan, stok dikembalikan.");
    }
}
