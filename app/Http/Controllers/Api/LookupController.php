<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BatchStok;
use App\Models\Obat;
use App\Models\StokFaskes;
use App\Models\StokGudang;
use App\Services\FefoService;
use App\Support\FaskesAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Cari obat aktif untuk picker form. Respons: [{value, label}].
     */
    public function obat(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $obat = Obat::query()
            ->where('status', 'aktif')
            ->when($q !== '', fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('kode_obat', 'like', "%{$q}%")
                    ->orWhere('nama_obat', 'like', "%{$q}%");
            }))
            ->orderBy('nama_obat')
            ->limit(20)
            ->get(['id', 'kode_obat', 'nama_obat', 'satuan']);

        return response()->json(
            $obat->map(fn (Obat $o) => [
                'value' => $o->id,
                'label' => "{$o->kode_obat} — {$o->nama_obat} ({$o->satuan})",
            ])
        );
    }

    /**
     * Batch tersedia untuk obat + faskes, urut sesuai metode stok obat.
     * Faskes di luar wewenang user ditolak.
     */
    public function batch(Request $request, FefoService $fefo): JsonResponse
    {
        $data = $request->validate([
            'obat_id' => ['required', 'integer', 'exists:obat,id'],
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
        ]);

        $fasilitasId = $data['fasilitas_id'] ?? null;

        if (filled($request->user()->fasilitas_kesehatan_id)
            && ! in_array($fasilitasId, FaskesAccess::visibleFasilitasIds($request->user()), true)) {
            return response()->json(['message' => 'Faskes di luar wewenang.'], 403);
        }

        $obat = Obat::find($data['obat_id']);
        $metode = $obat->metode_stok->value ?? 'fefo';

        $batches = $fefo->getAvailableBatches($data['obat_id'], $fasilitasId, $metode);

        return response()->json(
            $batches->map(fn (BatchStok $b) => [
                'value' => $b->id,
                'label' => sprintf(
                    '%s (Exp: %s, Sisa: %s)',
                    $b->batch_number,
                    $b->tanggal_expired->format('d/m/Y'),
                    number_format($b->jumlah, 0, ',', '.'),
                ),
                'batch_number' => $b->batch_number,
                'tanggal_expired' => $b->tanggal_expired->format('Y-m-d'),
                'jumlah' => $b->jumlah,
            ])
        );
    }

    /**
     * Stok sistem (agregat) untuk obat + faskes. Dipakai auto-fill form opname.
     */
    public function stokSistem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'obat_id' => ['required', 'integer', 'exists:obat,id'],
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
        ]);

        $fasilitasId = $data['fasilitas_id'] ?? null;

        if (filled($request->user()->fasilitas_kesehatan_id)
            && ! in_array($fasilitasId, FaskesAccess::visibleFasilitasIds($request->user()), true)) {
            return response()->json(['message' => 'Faskes di luar wewenang.'], 403);
        }

        $jumlah = $fasilitasId
            ? StokFaskes::where('fasilitas_id', $fasilitasId)->where('obat_id', $data['obat_id'])->value('jumlah')
            : StokGudang::where('obat_id', $data['obat_id'])->value('jumlah');

        return response()->json(['jumlah' => (int) ($jumlah ?? 0)]);
    }

    /**
     * Preview alokasi batch (FEFO/FIFO/LIFO) untuk form distribusi.
     * Server mengalokasikan ulang saat submit; respons ini hanya tampilan.
     */
    public function alokasi(Request $request, FefoService $fefo): JsonResponse
    {
        $data = $request->validate([
            'obat_id' => ['required', 'integer', 'exists:obat,id'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'fasilitas_id' => ['nullable', 'integer', 'exists:fasilitas_kesehatan,id'],
        ]);

        $fasilitasId = $data['fasilitas_id'] ?? null;

        if (filled($request->user()->fasilitas_kesehatan_id)
            && ! in_array($fasilitasId, FaskesAccess::visibleFasilitasIds($request->user()), true)) {
            return response()->json(['message' => 'Faskes di luar wewenang.'], 403);
        }

        $obat = Obat::find($data['obat_id']);
        $metode = $obat->metode_stok->value ?? 'fefo';

        $alokasi = $fefo->allocate($data['obat_id'], $data['jumlah'], $fasilitasId, $metode);

        $batches = BatchStok::whereKey(collect($alokasi)->pluck('batch_id'))->get()->keyBy('id');

        return response()->json(
            collect($alokasi)->map(fn (array $item) => [
                'batch_id' => $item['batch_id'],
                'jumlah' => $item['jumlah'],
                'batch_number' => $batches->get($item['batch_id'])?->batch_number,
                'tanggal_expired' => $batches->get($item['batch_id'])?->tanggal_expired?->format('Y-m-d'),
            ])->values()
        );
    }
}
