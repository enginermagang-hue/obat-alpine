<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Jobs\ImportObatJob;
use App\Models\Obat;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportObatController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Obat::class);

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih berkas CSV terlebih dahulu.',
            'file.mimes' => 'Berkas harus berformat CSV.',
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $stored = $file->storeAs('imports', now()->format('YmdHis').'-'.$file->getClientOriginalName());

        $importId = DB::table('imports')->insertGetId([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $stored,
            'importer' => 'obat',
            'total_rows' => 0,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'user_id' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ImportObatJob::dispatch($importId);

        return redirect()->route('obat.index')->with('success', 'Berkas diterima. Import berjalan di latar, refresh halaman untuk melihat progres.');
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', Obat::class);

        $headers = ['kode_obat', 'nama_obat', 'nama_generik', 'kategori', 'satuan', 'kekuatan', 'bentuk_sediaan', 'produsen', 'kemasan', 'harga_satuan', 'status', 'ven_kategori', 'metode_stok'];
        $example = ['92000146', 'Albendazole Tablet 400 mg', 'Albendazole', 'Antelmintik', 'Tablet', '400 mg', 'tablet', 'PT Kimia Farma', 'Strip isi 10', '2500', 'aktif', 'E', 'fefo'];

        return response()->streamDownload(function () use ($headers, $example): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, $example);
            fclose($handle);
        }, 'template-import-obat.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Status import terakhir untuk kartu progres di halaman index.
     *
     * @return array<string, mixed>|null
     */
    public static function latestStatus(int $userId): ?array
    {
        $row = DB::table('imports')
            ->where('importer', 'obat')
            ->where('user_id', $userId)
            ->latest('id')
            ->first();

        if (! $row) {
            return null;
        }

        return (array) $row;
    }
}
