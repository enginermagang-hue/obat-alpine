<?php

namespace App\Services;

class DokumentasiPrediksiDataService
{
    public const BULAN_ID = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    public const BULAN_SINGKAT = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    public static function fmtId(float|int $x, int $digits = 2): string
    {
        $s = number_format((float) $x, $digits, '.', ',');

        return str_replace(',', 'X', str_replace('.', ',', str_replace('X', '.', $s)));
        // actually: number_format with '.' decimal then swap => use trick like Python
    }

    /**
     * Python fmt_id: f"{x:,.{digits}f}".replace(",","X").replace(".",",").replace("X",".")
     * number_format already gives "7,192,067.00" -> replace accordingly
     */
    public static function fmtIdCorrect(float|int $x, int $digits = 2): string
    {
        $s = number_format((float) $x, $digits, '.', ',');

        return str_replace(',', 'X', str_replace('.', ',', str_replace(',', 'X', ''))) ? '' : '';
    }

    public static function fmtIdReal(float|int $x, int $digits = 2): string
    {
        $s = sprintf('%01.'.$digits.'f', (float) $x);
        // add thousands separator manually like Python: 7192067.00 -> 7,192,067.00
        $parts = explode('.', $s);
        $int = $parts[0];
        $dec = $parts[1] ?? '';
        $neg = str_starts_with($int, '-');
        if ($neg) {
            $int = substr($int, 1);
        }
        $int = strrev(implode(',', str_split(strrev($int), 3)));
        // remove leading comma artifacts: str_split may produce correct
        $intFormatted = number_format((float) ($neg ? '-'.$int : $int), 0, '', ',');
        // simpler: use number_format then swap
        $tmp = number_format((float) $x, $digits, '.', ',');
        $tmp = str_replace(',', 'X', $tmp);
        $tmp = str_replace('.', ',', $tmp);
        $tmp = str_replace('X', '.', $tmp);

        return $tmp;
    }

    public static function fmtAuto(float|int $x): string
    {
        $r = round((float) $x, 2);
        if ($r == (int) $r) {
            return self::fmtReal($r, 0);
        }
        if (round((float) $x, 1) == $r) {
            return self::fmtReal($r, 1);
        }

        return self::fmtReal($r, 2);
    }

    public static function fmtReal(float|int $x, int $digits): string
    {
        $tmp = number_format((float) $x, $digits, '.', ',');
        $tmp = str_replace(',', 'X', $tmp);
        $tmp = str_replace('.', ',', $tmp);
        $tmp = str_replace('X', '.', $tmp);

        return $tmp;
    }

    public static function mean(array $xs): float
    {
        if ($xs === []) {
            return 0.0;
        }

        return array_sum($xs) / count($xs);
    }

    public static function pstdev(array $xs): float
    {
        if ($xs === []) {
            return 0.0;
        }
        $m = self::mean($xs);

        return sqrt(array_sum(array_map(fn ($x) => ($x - $m) ** 2, $xs)) / count($xs));
    }

    public static function sstdev(array $xs): float
    {
        if (count($xs) <= 1) {
            return 0.0;
        }
        $m = self::mean($xs);

        return sqrt(array_sum(array_map(fn ($x) => ($x - $m) ** 2, $xs)) / (count($xs) - 1));
    }

    /**
     * Prediksi tiap bulan = rata-rata 3 bulan aktual sebelumnya (dibulatkan).
     */
    public static function walkForwardMa(array $seri): array
    {
        $out = [];
        $n = count($seri);
        for ($i = 3; $i < $n; $i++) {
            $out[] = (int) round(self::mean(array_slice($seri, $i - 3, 3)));
        }

        return $out;
    }

    /**
     * MAE/MAPE/R2 mengikuti konvensi sistem: aktual nol dilewati (MAPE), R2 dijepit 0-1.
     */
    public static function evalMetrics(array $aktual, array $prediksi): array
    {
        $n = count($aktual);
        $selisih = [];
        foreach ($aktual as $i => $a) {
            $selisih[] = abs($a - ($prediksi[$i] ?? 0));
        }
        $mae = array_sum($selisih) / max(1, $n);
        $rasio = [];
        foreach ($selisih as $i => $e) {
            $a = $aktual[$i];
            if ($a != 0) {
                $rasio[] = $e / $a;
            }
        }
        $mape = $rasio !== [] ? array_sum($rasio) / count($rasio) * 100 : null;
        $rata = self::mean($aktual);
        $ssTot = array_sum(array_map(fn ($a) => ($a - $rata) ** 2, $aktual));
        $ssRes = 0.0;
        foreach ($aktual as $i => $a) {
            $p = $prediksi[$i] ?? 0;
            $ssRes += ($a - $p) ** 2;
        }
        $r2Raw = $ssTot ? (1 - $ssRes / $ssTot) : 0.0;
        $r2 = max(0.0, $r2Raw);

        return [
            'mae' => $mae,
            'mape' => $mape,
            'r2' => $r2,
            'r2_raw' => $r2Raw,
            'ss_res' => $ssRes,
            'ss_tot' => $ssTot,
            'sum_ae' => array_sum($selisih),
            'avg' => $rata,
            'selisih' => $selisih,
            'n_nonzero' => count($rasio),
        ];
    }

    /**
     * Forecast iteratif: rata-rata 3 bulan terakhir, hasil ikut menjadi lag berikutnya.
     */
    public static function maRollout(array $tigaTerakhir, int $horizon = 3): array
    {
        $hist = array_values($tigaTerakhir);
        $keluar = [];
        for ($i = 0; $i < $horizon; $i++) {
            $f = (int) round(self::mean(array_slice($hist, -3, 3)));
            $keluar[] = $f;
            $hist[] = $f;
        }

        return $keluar;
    }

    public static function labelBulan(string $kunci): string
    {
        [$tahun, $bulan] = explode('-', $kunci);

        return self::BULAN_ID[(int) $bulan - 1].' '.$tahun;
    }

    /**
     * Deret bulanan obat ke-idx dari JSON, urut kronologis. Return [kunci, nilai, info].
     */
    public static function seriObat(array $data, int $idx = 0): array
    {
        $info = $data['obat'][$idx];
        $pemakaian = $data['pemakaian'][(string) $info['id']];
        $kunci = array_keys($pemakaian);
        sort($kunci);
        $nilai = [];
        foreach ($kunci as $k) {
            $nilai[] = (int) $pemakaian[$k];
        }

        return [$kunci, $nilai, $info];
    }

    public static function gridTahun(array $kunci, array $nilai): array
    {
        $grid = [];
        foreach ($kunci as $i => $k) {
            [$tahun, $bulan] = explode('-', $k);
            $tahun = (int) $tahun;
            if (! isset($grid[$tahun])) {
                $grid[$tahun] = array_fill(0, 12, 0);
            }
            $grid[$tahun][(int) $bulan - 1] = $nilai[$i];
        }

        return $grid;
    }

    public static function niceStep(float $span, int $target = 7): int
    {
        foreach ([10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000] as $step) {
            if ($span / $step <= $target) {
                return $step;
            }
        }

        return 5000;
    }

    /**
     * Build all derived view data like Python build() 733-782.
     */
    public static function buildViewData(array $data): array
    {
        $faskesNama = $data['faskes']['nama'];
        $tipeFaskesNilai = $data['faskes']['tipe'] === 'puskesmas' ? 1 : 0;

        [$kunci, $seri, $infoUtama] = self::seriObat($data, 0);
        $nama0 = $infoUtama['nama'];
        $satuan0 = $infoUtama['satuan'];
        $nama1 = isset($data['obat'][1]) ? $data['obat'][1]['nama'] : '-';
        $nama2 = isset($data['obat'][2]) ? $data['obat'][2]['nama'] : '-';

        $grid = self::gridTahun($kunci, $seri);
        $tahunList = array_keys($grid);
        sort($tahunList);
        $nBulan = count($seri);
        $mu = self::mean($seri);
        $sd = self::pstdev($seri);
        $mn = min($seri);
        $mx = max($seri);
        $mnIdx = array_search($mn, $seri, true);
        $mxIdx = array_search($mx, $seri, true);
        $mnLabel = self::labelBulan($kunci[$mnIdx]);
        $mxLabel = self::labelBulan($kunci[$mxIdx]);

        $nVektor = $nBulan - 3;
        $nTrain = (int) floor($nVektor * 0.8);
        $nTest = $nVektor - $nTrain;

        $aktual2024 = array_slice($seri, -12);
        $pred2024 = array_slice(self::walkForwardMa($seri), -12);
        $met = self::evalMetrics($aktual2024, $pred2024);
        $evalLabel = self::labelBulan($kunci[count($kunci) - 12]).'-'.self::labelBulan($kunci[count($kunci) - 1]);
        $evalPendek = self::BULAN_SINGKAT[(int) explode('-', $kunci[count($kunci) - 12])[1] - 1].' '.explode('-', $kunci[count($kunci) - 12])[0].'-'.self::BULAN_SINGKAT[(int) explode('-', $kunci[count($kunci) - 1])[1] - 1].' '.explode('-', $kunci[count($kunci) - 1])[0];

        $tiga = array_slice($seri, -3);
        $fc = self::maRollout($tiga);
        $sdHist = self::sstdev($seri);
        $marginFc = (int) round(1.96 * $sdHist);
        $fcLo = array_map(fn ($v) => max(0, $v - $marginFc), $fc);
        $fcHi = array_map(fn ($v) => $v + $marginFc, $fc);

        $stok0 = (int) ($data['stok_sekarang'][(string) $infoUtama['id']] ?? 0);
        $totalT = array_sum($fc);
        $butuh = $totalT * 1.20;
        $rekom = max(0, (int) ceil($butuh - $stok0));
        $rataBln = $totalT / 3;
        $coverage = $rataBln ? ($stok0 / $rataBln) * 30.44 : 0.0;
        $statusStok = $rekom <= 0 ? 'Aman' : ($coverage < 21 ? 'Kritis' : 'Perlu Pesan');
        $histMax = max($seri);
        $histMean = self::mean($seri);
        $capCap = (int) ceil(max($histMax * 3, $histMean * 5, 50));
        $model0 = $data['model'][(string) $infoUtama['id']] ?? null;

        $ringkas = [];
        $limit = min(3, count($data['obat']));
        for ($i = 0; $i < $limit; $i++) {
            [$kk, $ss, $info] = self::seriObat($data, $i);
            $mm = self::evalMetrics(array_slice($ss, -12), array_slice(self::walkForwardMa($ss), -12));
            $ff = self::maRollout(array_slice($ss, -3));
            $sdh = self::sstdev($ss);
            $mg = (int) round(1.96 * $sdh);
            $ringkas[] = [
                'info' => $info,
                'met' => $mm,
                'fc' => $ff,
                'lo' => array_map(fn ($v) => max(0, $v - $mg), $ff),
                'hi' => array_map(fn ($v) => $v + $mg, $ff),
                'sd' => $sdh,
                'margin' => $mg,
            ];
        }

        $thnFc = end($tahunList) + 1;
        $namaFc = [self::BULAN_SINGKAT[0].' '.$thnFc, self::BULAN_SINGKAT[1].' '.$thnFc, self::BULAN_SINGKAT[2].' '.$thnFc];
        $desLabel = self::labelBulan(end($kunci));

        return [
            'faskesNama' => $faskesNama,
            'tipeFaskesNilai' => $tipeFaskesNilai,
            'kunci' => $kunci,
            'seri' => $seri,
            'infoUtama' => $infoUtama,
            'nama0' => $nama0,
            'satuan0' => $satuan0,
            'nama1' => $nama1,
            'nama2' => $nama2,
            'grid' => $grid,
            'tahunList' => $tahunList,
            'nBulan' => $nBulan,
            'mu' => $mu,
            'sd' => $sd,
            'mn' => $mn,
            'mx' => $mx,
            'mnLabel' => $mnLabel,
            'mxLabel' => $mxLabel,
            'nVektor' => $nVektor,
            'nTrain' => $nTrain,
            'nTest' => $nTest,
            'aktual2024' => $aktual2024,
            'pred2024' => $pred2024,
            'met' => $met,
            'evalLabel' => $evalLabel,
            'evalPendek' => $evalPendek,
            'tiga' => $tiga,
            'fc' => $fc,
            'sdHist' => $sdHist,
            'marginFc' => $marginFc,
            'fcLo' => $fcLo,
            'fcHi' => $fcHi,
            'stok0' => $stok0,
            'totalT' => $totalT,
            'butuh' => $butuh,
            'rekom' => $rekom,
            'rataBln' => $rataBln,
            'coverage' => $coverage,
            'statusStok' => $statusStok,
            'histMax' => $histMax,
            'histMean' => $histMean,
            'capCap' => $capCap,
            'model0' => $model0,
            'ringkas' => $ringkas,
            'thnFc' => $thnFc,
            'namaFc' => $namaFc,
            'desLabel' => $desLabel,
            'data' => $data,
        ];
    }
}
