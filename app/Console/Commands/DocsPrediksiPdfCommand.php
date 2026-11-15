<?php

namespace App\Console\Commands;

use App\Services\DokumentasiFigureService;
use App\Services\DokumentasiPrediksiDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\LaravelPdf\Facades\Pdf;

class DocsPrediksiPdfCommand extends Command
{
    protected $signature = 'docs:prediksi-pdf
        {--json=tmp/prediksi-data.json : Path JSON dari docs:prediksi-json (relatif project root)}
        {--output=tmp/Dokumentasi-Prediksi-AI-Dompdf.pdf : Path output PDF (relatif project root)}';

    protected $description = 'Generate PDF dokumentasi Prediksi AI (PHP + DomPDF, versi tandingan scripts/generate_prediksi_pdf.py)';

    public function handle(): int
    {
        $jsonRel = (string) $this->option('json');
        $outputRel = (string) $this->option('output');

        $jsonPath = base_path($jsonRel);
        $outputPath = base_path($outputRel);

        if (! File::exists($jsonPath)) {
            $this->error("Data JSON tidak ditemukan: {$jsonRel}");
            $this->line('Jalankan dulu: php artisan docs:prediksi-json --fasilitas-id=6 --obat-id=44,19,11');

            return self::FAILURE;
        }

        $raw = File::get($jsonPath);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $this->error("JSON rusak: {$jsonRel}");

            return self::FAILURE;
        }

        foreach (['faskes', 'obat', 'pemakaian', 'stok_sekarang'] as $kunci) {
            if (! array_key_exists($kunci, $data)) {
                $this->error("Struktur {$jsonRel} tidak valid: kunci '{$kunci}' hilang. Generate ulang via artisan.");

                return self::FAILURE;
            }
        }

        if (empty($data['obat'])) {
            $this->error("Struktur {$jsonRel} tidak valid: daftar obat kosong.");

            return self::FAILURE;
        }

        $vd = DokumentasiPrediksiDataService::buildViewData($data);

        // Build SVG figures
        $figureStages = DokumentasiFigureService::svgStages();
        $figureArch = DokumentasiFigureService::svgArch();
        $figureTraining = DokumentasiFigureService::svgTraining();
        $figureActualPred = DokumentasiFigureService::svgActualPred(
            $vd['aktual2024'],
            $vd['pred2024'],
            DokumentasiPrediksiDataService::BULAN_SINGKAT,
            $vd['evalPendek']
        );
        $figureForecast = DokumentasiFigureService::svgForecast(
            $vd['fc'],
            $vd['fcLo'],
            $vd['fcHi'],
            $vd['namaFc']
        );

        // Guard: file terkunci (Windows)
        $dir = dirname($outputPath);
        File::ensureDirectoryExists($dir);

        if (File::exists($outputPath)) {
            try {
                $fh = fopen($outputPath, 'ab');
                if ($fh === false) {
                    throw new \RuntimeException('fopen gagal');
                }
                fclose($fh);
            } catch (\Throwable) {
                $this->error("Gagal menulis {$outputRel}: file sedang dibuka aplikasi lain. Tutup dulu lalu jalankan ulang.");

                return self::FAILURE;
            }
        }

        $viewData = array_merge($vd, [
            'figureStages' => $figureStages,
            'figureArch' => $figureArch,
            'figureTraining' => $figureTraining,
            'figureActualPred' => $figureActualPred,
            'figureForecast' => $figureForecast,
        ]);

        try {
            Pdf::view('pdf.dokumentasi-prediksi', $viewData)
                ->format('A4')
                ->save($outputPath);
        } catch (\Throwable $e) {
            $this->error('Gagal generate PDF: '.$e->getMessage());

            return self::FAILURE;
        }

        $size = File::exists($outputPath) ? filesize($outputPath) : 0;

        $this->info("OK: {$outputRel} (".number_format($size).' bytes)');
        $this->line('Sumber: '.$jsonRel);

        return self::SUCCESS;
    }
}
