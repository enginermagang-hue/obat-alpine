<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Dokumentasi Prediksi Kebutuhan Obat</title>
<style>
@page { margin: 20mm 22mm 20mm 22mm; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; line-height: 1.45; color: #1e1e1e; margin:0; padding:0; }
h1.doc-title { text-align: center; color: #1e3a5f; font-size: 14pt; line-height: 1.3; margin: 0 0 8px; }
.doc-title-line { width: 160px; height: 2px; background: #1e3a5f; margin: 0 auto 14px; }
h2.doc-h2 { color: #1e3a5f; font-size: 11pt; border-bottom: 1.2px solid #b4becd; padding-bottom: 4px; margin: 16px 0 8px; }
h3.doc-h3 { color: #1e1e1e; font-size: 10pt; margin: 12px 0 6px; }
p.doc-para { text-align: justify; margin: 0 0 6px; font-size: 9pt; line-height: 1.5; }
p.doc-para b { font-weight: 700; }
.doc-cap { text-align: center; font-weight: 700; font-size: 9pt; margin: 8px 0 4px; }
.doc-rumus { text-align: center; font-size: 9pt; margin: 6px 0 8px; }
.doc-rumus b { font-weight: 700; }
.doc-figure { text-align: center; margin: 6px 0 4px; }
.doc-figure img { max-width: 100%; }
table.doc-table { width: 100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 8px; }
table.doc-table th { background-color: #254878; color: #ffffff; padding: 4px 5px; text-align: left; font-weight: 700; font-size: 7.5pt; }
table.doc-table td { padding: 3px 5px; border-bottom: 0.5px solid #d1d5db; vertical-align: middle; }
table.doc-table tr:nth-child(even) td { background-color: #ecf2f9; }
.doc-footer { position: fixed; bottom: -14mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #6e6e6e; border-top: 0.5px solid #b4becd; padding-top: 4px; font-style: italic; }
.doc-ref { font-size: 8.5pt; text-align: justify; margin: 0 0 4px; }
</style>
</head>
<body>
<div class="doc-footer">Dokumentasi Fitur Prediksi Kebutuhan Obat dengan Kecerdasan Buatan &nbsp;|&nbsp; Halaman <span class="page-number"></span></div>
<script type="text/php">
if (isset($pdf)) { $pdf->page_script('if ($PAGE_COUNT > 0) { $font = $fontMetrics->get_font("DejaVu Sans", "italic"); $pdf->text(270, 810, "Halaman $PAGE_NUM / $PAGE_COUNT", $font, 7, array(0.43,0.43,0.43)); }'); }
</script>

@php
use App\Services\DokumentasiPrediksiDataService as D;
use App\Services\DokumentasiFigureService as F;
@endphp

<h1 class="doc-title">Prediksi Kebutuhan Obat Menggunakan Artificial Neural Network<br>pada Sistem Informasi Manajemen Stok Obat</h1>
<div class="doc-title-line"></div>

<h3 class="doc-h3">Abstrak</h3>
<p class="doc-para">Prediksi jumlah kebutuhan obat pada fasilitas kesehatan sangat dibutuhkan untuk menjamin ketersediaan obat bagi pasien sekaligus menghindari penumpukan stok kedaluwarsa. Dokumen ini mendeskripsikan fitur prediksi kebutuhan obat yang menerapkan Artificial Neural Network (ANN) jenis Multilayer Perceptron berarsitektur 9-12-8-1, ditulis murni dengan PHP tanpa library machine learning eksternal. Sumber data adalah pencatatan pemakaian obat yang diagregasi bulanan selama 12 bulan, direkayasa menjadi 9 fitur lag/tren/stok, dinormalisasi dengan z-score, dan dilatih dengan stochastic gradient descent disertai early stopping. Model dievaluasi dengan R<sup>2</sup>, MAE, dan MAPE; prediksi dibuat 3 bulan ke depan secara autoregresif dengan fallback moving average berpenguatan musiman bila data belum cukup atau akurasi model di bawah ambang (R<sup>2</sup> &lt; 0,30), lalu diintegrasikan ke RKO dan permintaan obat.</p>
<p class="doc-para"><b>Kata kunci:</b> Prediksi Kebutuhan Obat; Artificial Neural Network; Backpropagation; Moving Average; R<sup>2</sup>; MAE; MAPE; RKO.</p>

<h3 class="doc-h3">Abstract</h3>
<p class="doc-para">Predicting drug demand at health facilities is essential to guarantee drug availability for patients while avoiding expired-stock accumulation. This document describes a drug-demand prediction feature implementing a 9-12-8-1 Multilayer Perceptron Artificial Neural Network (ANN), written purely in PHP without external machine learning libraries. Drug usage records aggregated monthly over 12 months are engineered into 9 lag/trend/stock features, normalized with z-score, and trained with stochastic gradient descent with early stopping. Models are evaluated with R<sup>2</sup>, MAE, and MAPE; predictions cover 3 months ahead autoregressively with a seasonally-adjusted moving-average fallback when data are insufficient or model accuracy is below threshold (R<sup>2</sup> &lt; 0.30), and are integrated into drug requirement planning (RKO) and drug requisitions.</p>
<p class="doc-para"><b>Keywords:</b> Drug Demand Prediction; Artificial Neural Network; Backpropagation; Moving Average; R<sup>2</sup>; MAE; MAPE; RKO.</p>

<h2 class="doc-h2">1. Pendahuluan</h2>
<p class="doc-para">Sistem informasi manajemen stok obat melayani Gudang Dinas, Puskesmas, dan Pustu. Tantangan utamanya adalah menentukan jumlah kebutuhan obat setiap periode secara akurat: kekurangan stok menghambat pelayanan pasien, sedangkan kelebihan stok berisiko kedaluwarsa dan memboroskan anggaran. Selama ini Rencana Kebutuhan Obat (RKO) disusun berbasis rekapitulasi manual sehingga kurang responsif terhadap pola konsumsi aktual.</p>
<p class="doc-para">Perkembangan Artificial Intelligence (AI) menghadirkan beragam teknik prediksi, salah satunya Artificial Neural Network (ANN) yang mengadopsi cara kerja jaringan syaraf. Algoritma Backpropagation Neural Network (BPNN) termasuk yang terbaik untuk prediksi, termasuk prediksi kebutuhan obat pada layanan kesehatan. Masalah yang diselesaikan adalah bagaimana merancang arsitektur ANN yang tepat beserta parameternya sehingga menghasilkan prediksi akurat, serta bagaimana hasil prediksi tersebut dapat langsung dipakai dalam perencanaan resmi faskes.</p>
<p class="doc-para">Dokumen ini bertujuan mendeskripsikan rancangan dan implementasi fitur prediksi kebutuhan obat pada sistem informasi manajemen stok obat: pengumpulan data, analisis data, perancangan arsitektur ANN, pelaksanaan prediksi, hasil dan pembahasan, serta kesimpulan.</p>

<h2 class="doc-h2">2. Metodologi Penelitian</h2>
<p class="doc-para">Tahapan prediksi kebutuhan obat terdiri dari pengumpulan data, analisis data, perancangan model arsitektur ANN, dan pelaksanaan prediksi, sebagaimana disajikan pada Gambar 1. Implementasi memakai PHP murni (Laravel 13, PHP 8.4, panel admin Filament) dengan basis data MySQL untuk pengembangan dan produksi; SQLite hanya dipakai untuk pengujian otomatis; penjadwalan training memakai Laravel Scheduler tiap Minggu jam 02:00.</p>

<div class="doc-cap">Tabel 1. Lingkungan implementasi</div>
<table class="doc-table">
<thead><tr><th style="width:28%">Komponen</th><th>Teknologi</th></tr></thead>
<tbody>
<tr><td>Bahasa implementasi AI</td><td>PHP (murni, tanpa library machine learning eksternal)</td></tr>
<tr><td>Framework aplikasi</td><td>Laravel 13, PHP 8.4, panel admin Filament</td></tr>
<tr><td>Basis data</td><td>MySQL untuk pengembangan dan produksi; SQLite hanya untuk testing</td></tr>
<tr><td>Model ANN</td><td>Kode sendiri: AnnTrainer (training), AnnModel (inferensi), AnnScaler (normalisasi)</td></tr>
<tr><td>Metode cadangan</td><td>MovingAverageService (rata-rata bergerak + confidence interval)</td></tr>
<tr><td>Penjadwalan</td><td>Laravel Scheduler: perintah ai:train-models tiap Minggu jam 02:00</td></tr>
</tbody>
</table>

<div class="doc-figure"><img src="data:image/svg+xml;base64,{{ base64_encode($figureStages) }}" style="width:100%"></div>
<div class="doc-cap">Gambar 1. Tahapan prediksi kebutuhan obat</div>

<h3 class="doc-h3">2.1. Pengumpulan Data</h3>
<p class="doc-para">Data dikumpulkan dari pencatatan pemakaian obat (tabel pemakaian_obat dan detail_pemakaian_obat) per kombinasi fasilitas kesehatan dan obat. Setiap pemakaian otomatis mengurangi stok sehingga data ini mencerminkan konsumsi nyata. Untuk tiap kombinasi, sistem mengagregasi SUM(jumlah) per bulan selama 12 bulan terakhir, dihitung mundur dari tanggal pemakaian terakhir pada faskes tersebut; bulan tanpa catatan diisi nol (zero-fill) agar deret kalender tetap utuh.</p>
<p class="doc-para">Data yang sama tercatat sebagai riwayat keluar dan menjadi dasar penyusunan LPLPO (Laporan Pemakaian dan Lembar Permintaan Obat), sehingga sumber training selaras dengan dokumen perencanaan resmi faskes sebagaimana dipakai pada penelitian acuan.</p>
<p class="doc-para">Mengikuti penelitian acuan, tiga obat terbanyak dijadikan sampel: {{ $nama0 }} (ditampilkan penuh pada Bab 3), serta {{ $nama1 }} dan {{ $nama2 }} yang diperlakukan dengan proses yang sama. Ketiganya adalah obat dengan pemakaian terbesar pada {{ $faskesNama }} sehingga menjadi wakil paling representatif untuk pengujian prediksi.</p>

<div class="doc-cap">Tabel 2. Struktur penyimpanan data prediksi (MySQL)</div>
<table class="doc-table">
<thead><tr><th style="width:24%">Tabel</th><th>Peran dan constraint kunci</th></tr></thead>
<tbody>
<tr><td>model_prediksi</td><td>Satu baris per kombinasi faskes+obat (unik); FK cascade ke fasilitas_kesehatan dan obat; index (status, tanggal_training); akurasi_r2 DECIMAL(5,4), mae DECIMAL(10,2), mape DECIMAL(5,2); status ENUM; bobot JSON di LONGTEXT + file</td></tr>
<tr><td>prediksi_kebutuhan</td><td>Unik (fasilitas_id, obat_id, periode_bulan, periode_tahun); FK model_id nullOnDelete; index kolom metode (ENUM); dibuat_oleh nullable (NULL = system-generated)</td></tr>
<tr><td>pemakaian_obat + detail</td><td>Sumber training; agregasi memakai DATE_FORMAT(tanggal_pemakaian, '%Y-%m') pada MySQL (strftime pada SQLite)</td></tr>
<tr><td>detail_rko</td><td>Konsumen hasil: prediksi_id nullable menautkan usulan RKO ke baris prediksi</td></tr>
</tbody>
</table>
<p class="doc-para">Perubahan tipe ENUM (mis. penambahan metode ann_php) dijalankan via ALTER TABLE ... MODIFY ENUM khusus MySQL dan dilewati pada SQLite, karena kode AI ditulis dual-driver.</p>

<h3 class="doc-h3">2.2. Analisis Data</h3>
<p class="doc-para">Data yang dikumpulkan dianalisis dengan rekayasa fitur dan normalisasi. Dari deret 12 bulan dibentuk vektor 9 fitur untuk tiap titik waktu, sebagaimana Tabel 3.</p>

<div class="doc-cap">Tabel 3. Fitur masukan model</div>
<table class="doc-table">
<thead><tr><th style="width:8%">No</th><th style="width:26%">Fitur</th><th>Keterangan</th></tr></thead>
<tbody>
<tr><td>1</td><td>lag_1_bulan</td><td>Pemakaian satu bulan sebelumnya</td></tr>
<tr><td>2</td><td>lag_2_bulan</td><td>Pemakaian dua bulan sebelumnya</td></tr>
<tr><td>3</td><td>lag_3_bulan</td><td>Pemakaian tiga bulan sebelumnya</td></tr>
<tr><td>4</td><td>rata_rata_6_bulan</td><td>Rata-rata pemakaian 6 bulan terakhir</td></tr>
<tr><td>5</td><td>rata_rata_12_bulan</td><td>Rata-rata pemakaian 12 bulan terakhir</td></tr>
<tr><td>6</td><td>bulan</td><td>Nomor bulan 1-12 (dipertahankan untuk kompatibilitas model lama)</td></tr>
<tr><td>7</td><td>trend_3_bulan</td><td>Kemiringan (slope) regresi linear 3 bulan terakhir</td></tr>
<tr><td>8</td><td>stok_saat_ini</td><td>Stok berjalan (stok faskes; cadangan batch/gudang)</td></tr>
<tr><td>9</td><td>tipe_faskes</td><td>1 untuk puskesmas, 0 untuk pustu</td></tr>
</tbody>
</table>
<p class="doc-para">Normalisasi dilakukan karena skala fitur sangat timpang (mis. lag ratusan unit vs tipe 0/1). Setiap fitur distandardisasi dengan z-score memakai mean dan std dari data training (AnnScaler); bila std nyaris nol (&lt; 1e-8, fitur konstan), pembaginya diganti 1,0. Target (jumlah pemakaian) juga dinormalisasi lalu dikembalikan (denormalisasi / post-processing) saat prediksi agar keluar dalam satuan asli; parameter normalisasi ikut disimpan bersama bobot model.</p>

<h3 class="doc-h3">2.3. Perancangan Model Arsitektur ANN</h3>
<p class="doc-para">Arsitektur yang dirancang adalah Multilayer Perceptron (MLP) 9-12-8-1, sebagaimana Tabel 4, dengan total 233 parameter yang dipelajari.</p>

<div class="doc-cap">Tabel 4. Rancangan arsitektur model ANN</div>
<table class="doc-table">
<thead><tr><th>Lapis</th><th>Neuron</th><th>Aktivasi</th><th>Parameter (bobot + bias)</th></tr></thead>
<tbody>
<tr><td>Masukan</td><td>9</td><td>-</td><td>-</td></tr>
<tr><td>Tersembunyi 1</td><td>12</td><td>ReLU</td><td>12 x 9 + 12 = 120</td></tr>
<tr><td>Tersembunyi 2</td><td>8</td><td>ReLU</td><td>8 x 12 + 8 = 104</td></tr>
<tr><td>Keluaran</td><td>1</td><td>Linear</td><td>1 x 8 + 1 = 9</td></tr>
</tbody>
</table>
<p class="doc-para">Pembelajaran mengikuti tahapan Backpropagation: inisialisasi bobot awal, tahap aktivasi (forward pass menghitung output aktual lapis tersembunyi dan lapis keluaran), weight training (menghitung gradien error lapis keluaran lalu lapis tersembunyi dan mengubah bobot), dan tahap iterasi (pengulangan epoch hingga error minimal atau early stopping). Bobot diinisialisasi metode He (w = randn x akar(2/fan_in), bias 0) yang adaptif per lapis; komposisi lapis tersembunyi 12-8 merupakan ketetapan konfigurasi sistem.</p>

<div class="doc-cap">Tabel 5. Perbandingan dengan penelitian acuan (BPNN prediksi obat Puskesmas)</div>
<table class="doc-table">
<thead><tr><th style="width:22%">Aspek</th><th>Penelitian acuan</th><th>Sistem ini</th></tr></thead>
<tbody>
<tr><td>Arsitektur</td><td>12-12-1 (12 input = 12 bulan)</td><td>9-12-8-1 (9 fitur lag/tren/stok)</td></tr>
<tr><td>Normalisasi</td><td>Min-max ke rentang [0.1, 0.9]</td><td>Z-score per fitur + target</td></tr>
<tr><td>Aktivasi</td><td>Logsig hidden, purelin output</td><td>ReLU hidden, linear output</td></tr>
<tr><td>Split data</td><td>70% training / 30% testing</td><td>80% training / 20% testing</td></tr>
<tr><td>Iterasi</td><td>Epoch 1000 tetap</td><td>Epoch 800 + early stopping patience 20</td></tr>
<tr><td>Pembelajaran</td><td>Learning rate 0,1 + momentum 0,9</td><td>SGD lr 0,01 + regularisasi L2</td></tr>
<tr><td>Perkakas</td><td>MATLAB</td><td>PHP murni (tanpa library ML)</td></tr>
<tr><td>Metrik</td><td>MAPE dan akurasi = 100% - MAPE</td><td>R2, MAE, MAPE (mentah)</td></tr>
<tr><td>Keluaran</td><td>Tabel prediksi tahunan</td><td>Prediksi 3 bulan + CI, terintegrasi RKO/permintaan</td></tr>
</tbody>
</table>

<div class="doc-cap">Tabel 6. Hiperparameter training</div>
<table class="doc-table">
<thead><tr><th style="width:26%">Aspek</th><th>Nilai / Ketentuan</th></tr></thead>
<tbody>
<tr><td>Learning rate</td><td>0,01</td></tr>
<tr><td>Epoch maksimal</td><td>800, dengan early stopping patience 20 epoch pada split validasi internal</td></tr>
<tr><td>Regularisasi L2</td><td>1e-4</td></tr>
<tr><td>Split train/test</td><td>80/20; metrik hanya dihitung bila test set berisi minimal 2 sampel</td></tr>
<tr><td>Syarat data</td><td>Minimal 6 bulan berbeda dengan pemakaian &gt; 0 dan minimal 2 vektor fitur</td></tr>
<tr><td>Jadwal</td><td>Cron tiap Minggu jam 02:00; maks 500 kombinasi per jalan; model aktif dilewati kecuali --force</td></tr>
</tbody>
</table>

<h3 class="doc-h3">2.4. Pelaksanaan Prediksi</h3>
<p class="doc-para">Setelah data valid dan arsitektur siap, prediksi dilaksanakan. Model ANN memprediksi 3 bulan ke depan secara autoregresif (hasil bulan ke-i menjadi lag bulan berikutnya) disertai confidence interval 95% (+-1,96 SD sampel historis); hasil dibulatkan dan tidak negatif. Bila data belum cukup atau akurasi model di bawah ambang (R<sup>2</sup> &lt; 0,30), dipakai moving average berpenguatan musiman: hasil MA dikali rasio rata-rata bulan kalender target terhadap rata-rata histori (dibatasi 0,6-1,8 agar tidak liar). Perbandingan kedua metode pada Tabel 7.</p>

<div class="doc-cap">Tabel 7. Metode prediksi</div>
<table class="doc-table">
<thead><tr><th style="width:18%">Aspek</th><th>ANN (ann_php)</th><th>Moving Average (moving_average)</th></tr></thead>
<tbody>
<tr><td>Syarat</td><td>Data &gt;= 6 bulan, model aktif, R<sup>2</sup> &gt;= 0,30 (di bawahnya fallback MA)</td><td>Data &lt; 6 bulan (status data_belum_cukup)</td></tr>
<tr><td>Cara kerja</td><td>Prediksi 3 bulan autoregresif; hasil bulan ke-i menjadi lag bulan berikutnya</td><td>Rata-rata 3 bulan terakhir x faktor musiman bulan target, diiterasi 3 bulan</td></tr>
<tr><td>Rentang</td><td>CI 95%: +-1,96 SD sampel historis</td><td>CI 95%: +-1,96 SD jendela (bawah &gt;= 0), diskalakan faktor yang sama</td></tr>
<tr><td>Keluaran</td><td>Bulat, tidak negatif</td><td>Bulat, tidak negatif</td></tr>
</tbody>
</table>
<p class="doc-para">Status model lain: kadaluarsa (digantikan training baru) dan gagal (pesan error tersimpan); keduanya tidak menghasilkan prediksi. Nilai metode lama (ai_gradient_boost, ai_random_forest) dipertahankan untuk data histori.</p>

<div class="doc-cap">Tabel 8. Metrik evaluasi per model</div>
<table class="doc-table">
<thead><tr><th style="width:22%">Metrik</th><th>Keterangan</th></tr></thead>
<tbody>
<tr><td>R<sup>2</sup> (akurasi_r2)</td><td>Koefisien determinasi 0-1; makin dekat 1 makin baik</td></tr>
<tr><td>MAE (mae)</td><td>Mean Absolute Error; rata-rata selisih absolut vs aktual</td></tr>
<tr><td>MAPE (mape)</td><td>Mean Absolute Percentage Error dalam persen; periode aktual nol dilewati</td></tr>
</tbody>
</table>
<p class="doc-para">Catatan konvensi: pada penelitian acuan, akurasi didefinisikan sebagai 100% dikurangi MAPE. Sistem ini menyimpan ketiga metrik dalam bentuk mentah (R<sup>2</sup>/MAE/MAPE) dan tidak menyimpan skor akurasi turunan tersebut; kolom Akurasi pada Tabel 19 dihitung sebagai 100% - MAPE hanya untuk perbandingan dengan penelitian acuan.</p>

<h2 class="doc-h2">3. Hasil dan Pembahasan</h2>
<h3 class="doc-h3">3.1. Data</h3>
<p class="doc-para">Tabel 9 menyajikan deret {{ $tahunList[count($tahunList)-1] - $tahunList[0] + 1 }} tahun ({{ $tahunList[0] }}-{{ end($tahunList) }}, {{ $nBulan }} bulan) untuk obat sampel utama {{ $nama0 }} (satuan {{ strtolower($satuan0) }}) yang dipakai pada seluruh contoh dokumen ini. Sumbernya adalah agregasi pencatatan pemakaian {{ $faskesNama }} per bulan; bulan tanpa catatan diisi nol (zero-fill) agar deret kalender tetap utuh. Untuk {{ $nama1 }} dan {{ $nama2 }} dilakukan proses yang sama; ringkasannya disajikan pada Tabel 19 dan Tabel 20 di akhir Bab 3. Nilai minimum {{ $mn }} ({{ $mnLabel }}) dan maksimum {{ $mx }} ({{ $mxLabel }}).</p>

<div class="doc-cap">Tabel 9. Data pemakaian {{ $nama0 }} {{ $tahunList[0] }}-{{ end($tahunList) }} ({{ strtolower($satuan0) }})</div>
<table class="doc-table">
<thead><tr><th>Bulan</th>@foreach($tahunList as $t)<th>{{ $t }}</th>@endforeach</tr></thead>
<tbody>
@for($m=0; $m<12; $m++)
<tr><td>{{ D::BULAN_ID[$m] }}</td>@foreach($tahunList as $t)<td>{{ $grid[$t][$m] }}</td>@endforeach</tr>
@endfor
</tbody>
</table>

<h3 class="doc-h3">3.2. Normalisasi Data</h3>
@php
$zMx = $sd > 1e-12 ? ($mx - $mu) / $sd : 0;
$zMn = $sd > 1e-12 ? ($mn - $mu) / $sd : 0;
@endphp
<p class="doc-para">Normalisasi memakai Rumus 1 dengan mean {{ D::fmtReal($mu,4) }} dan std populasi {{ D::fmtReal($sd,4) }} dari deret {{ $nBulan }} bulan {{ $nama0 }} pada Tabel 9; hasilnya pada Tabel 10 (format mengikuti Tabel 2 penelitian acuan). Contoh: {{ $mxLabel }} bernilai {{ $mx }} {{ strtolower($satuan0) }} memberi ({{ $mx }} - {{ D::fmtReal($mu,4) }}) / {{ D::fmtReal($sd,4) }} = {{ D::fmtReal($zMx,4) }}; {{ $mnLabel }} bernilai {{ $mn }} {{ strtolower($satuan0) }} memberi {{ D::fmtReal($zMn,4) }}.</p>
<div class="doc-rumus"><b>Rumus 1.</b> &nbsp; x' = (x - mean) / std</div>
<div class="doc-cap">Tabel 10. Data setelah normalisasi {{ $tahunList[0] }}-{{ end($tahunList) }} (z-score)</div>
<table class="doc-table">
<thead><tr><th>Bulan</th>@foreach($tahunList as $t)<th>{{ $t }}</th>@endforeach</tr></thead>
<tbody>
@for($m=0; $m<12; $m++)
<tr><td>{{ D::BULAN_ID[$m] }}</td>@foreach($tahunList as $t)<td>{{ D::fmtReal($sd > 1e-12 ? ($grid[$t][$m] - $mu)/$sd : 0, 4) }}</td>@endforeach</tr>
@endfor
</tbody>
</table>
@php $desTerakhir = $grid[end($tahunList)][11]; $mmVal = $mx > $mn ? 0.8 * ($desTerakhir - $mn)/($mx - $mn) + 0.1 : 0.1; @endphp
<p class="doc-para">Pembanding metode: dengan rumus min-max penelitian acuan (rentang 0.1-0.9; min {{ $mn }}, maks {{ $mx }}), nilai {{ $desTerakhir }} (Des {{ end($tahunList) }}) menjadi 0,8 x ({{ $desTerakhir }}-{{ $mn }})/({{ $mx }}-{{ $mn }}) + 0,1 = {{ D::fmtReal($mmVal,4) }}. Kedua metode sahih dan hanya berbeda skala; sistem memakai z-score.</p>

<h3 class="doc-h3">3.3. Penentuan Pola Data Latih dan Data Uji</h3>
<p class="doc-para">Deret {{ $nBulan }} bulan ({{ $tahunList[0] }}-{{ end($tahunList) }}) menghasilkan {{ $nVektor }} vektor fitur valid karena vektor pertama baru dapat dibentuk pada bulan ke-4 (membutuhkan lag_1 sampai lag_3). Split 80/20 memakai floor({{ $nVektor }} x 0,8) = {{ $nTrain }} vektor training dan {{ $nTest }} vektor testing; karena test set berisi minimal 2 sampel, ketiga metrik evaluasi terhitung. Pembagian 70/30 pada penelitian acuan setara dengan pola yang sama pada skala lebih panjang.</p>
<div class="doc-cap">Tabel 11. Pola data latih dan data uji ({{ $nBulan }} bulan, {{ $tahunList[0] }}-{{ end($tahunList) }})</div>
<table class="doc-table">
<thead><tr><th>Tahap</th><th>Perhitungan</th><th>Jumlah</th></tr></thead>
<tbody>
<tr><td>Deret bulanan</td><td>Jendela {{ $nBulan }} bulan</td><td>{{ $nBulan }} titik</td></tr>
<tr><td>Vektor fitur valid</td><td>Bulan ke-4 s/d ke-{{ $nBulan }}</td><td>{{ $nVektor }} vektor</td></tr>
<tr><td>Training (80%)</td><td>floor({{ $nVektor }} x 0,8)</td><td>{{ $nTrain }} vektor</td></tr>
<tr><td>Testing (20%)</td><td>{{ $nVektor }} - {{ $nTrain }}</td><td>{{ $nTest }} vektor</td></tr>
</tbody>
</table>
<p class="doc-para">Catatan: nilai R<sup>2</sup> hasil evaluasi dijepit pada rentang 0-1 sebelum disimpan. Bila test set berisi kurang dari 2 sampel, ketiga metrik dibiarkan NULL (belum terukur) dan model tetap dapat dipakai selama berstatus aktif.</p>

<h3 class="doc-h3">3.4. Perancangan Model Arsitektur ANN</h3>
<p class="doc-para">Rancangan mengikuti Tabel 4 dengan tahapan Backpropagation sebagaimana diuraikan pada 2.3. Struktur aliran model disajikan pada Gambar 2; legenda lambang pada Tabel 12; persamaan yang dipakai pada Rumus 2 sampai Rumus 4.</p>
<div class="doc-figure"><img src="data:image/svg+xml;base64,{{ base64_encode($figureArch) }}" style="width:100%"></div>
<div class="doc-cap">Gambar 2. Arsitektur model ANN 9-12-8-1 (9 input lag/tren/stok -&gt; 12 ReLU -&gt; 8 ReLU -&gt; 1 linear)</div>
<div class="doc-cap">Tabel 12. Notasi pada algoritma backpropagation</div>
<table class="doc-table">
<thead><tr><th style="width:20%">Simbol</th><th>Arti</th></tr></thead>
<tbody>
<tr><td>x</td><td>Vektor fitur masukan (9 fitur, lihat Tabel 3)</td></tr>
<tr><td>t</td><td>Nilai target (jumlah pemakaian aktual)</td></tr>
<tr><td>w</td><td>Bobot koneksi antar neuron</td></tr>
<tr><td>b</td><td>Bias / nilai ambang tiap neuron</td></tr>
<tr><td>z</td><td>Total masukan terbobot sebuah neuron</td></tr>
<tr><td>a</td><td>Keluaran aktivasi neuron tersembunyi</td></tr>
<tr><td>y</td><td>Keluaran prediksi neuron terakhir</td></tr>
<tr><td>lr</td><td>Learning rate / laju pembelajaran (0,01)</td></tr>
<tr><td>L2</td><td>Koefisien regularisasi bobot (1e-4)</td></tr>
<tr><td>grad (delta)</td><td>Gradien error hasil backpropagation</td></tr>
<tr><td>n</td><td>Jumlah sampel data</td></tr>
<tr><td>SD</td><td>Standar deviasi (simpangan baku)</td></tr>
<tr><td>T</td><td>Total prediksi pada horizon (rekomendasi)</td></tr>
<tr><td>S</td><td>Stok saat ini (rekomendasi)</td></tr>
</tbody>
</table>
<div class="doc-rumus"><b>Rumus 2.</b> &nbsp; z = jumlah(w x x) + b; &nbsp; a = max(0, z); &nbsp; y = jumlah(w x a) + b</div>
<div class="doc-rumus"><b>Rumus 3.</b> &nbsp; MSE = (1/n) x jumlah(y<sub>pred</sub> - y<sub>aktual</sub>)<sup>2</sup></div>
<div class="doc-rumus"><b>Rumus 4.</b> &nbsp; w &larr; w - lr x (grad + L2 x w)</div>
<p class="doc-para">Contoh satu neuron (ilustrasi): fitur lag_1 = 140 dengan mean 120 dan std 10 memberi z-score (140-120)/10 = 2,0. Neuron berbobot 0,5 dan bias -0,3 menghasilkan z = 0,5 x 2,0 - 0,3 = 0,7 dan aktivasi ReLU max(0; 0,7) = 0,7. Denormalisasi / post-processing: keluaran ternormalisasi 0,5 dengan yStd 20 dan yMean 100 menjadi 0,5 x 20 + 100 = 110 unit. Satu langkah SGD: w = 0,5 dengan gradien 0,4 menjadi 0,5 - 0,01 x (0,4 + 1e-4 x 0,5) = 0,4960. Inisialisasi He: lapis pertama (fan_in = 9) memakai simpangan akar(2/9) = 0,4714, mis. undian 0,63 memberi bobot awal 0,297.</p>
<div class="doc-figure"><img src="data:image/svg+xml;base64,{{ base64_encode($figureTraining) }}" style="width:100%"></div>
<div class="doc-cap">Gambar 3. Kurva pelatihan ilustratif (MSE train vs val; garis putus early stopping patience 20)</div>
<p class="doc-para">Gambar 3 mengilustrasikan dinamika pelatihan: MSE training turun mulus, MSE validasi turun lalu naik tipis setelah epoch ~420 sehingga early stopping menghentikan pada epoch ~520 (ilustrasi, bukan log riil). Polanya setara Gambar 3 penelitian acuan.</p>

<h3 class="doc-h3">3.5. Prediksi Kuantitas Kebutuhan Obat</h3>
@php $ma3 = (int) round(D::mean($tiga)); $sd3 = D::sstdev($tiga); $margin3 = (int) round(1.96 * $sd3); $awal3 = D::BULAN_ID[(int)explode('-',$kunci[count($kunci)-3])[1]-1]; $rentang3 = $awal3.'-'.$desLabel; @endphp
<p class="doc-para">Contoh moving average dari 3 bulan terakhir deret ({{ $rentang3 }}: {{ $tiga[0] }}, {{ $tiga[1] }}, {{ $tiga[2] }}) memberi rata-rata {{ $ma3 }}; SD sampel {{ D::fmtReal($sd3,2) }}; margin 1,96 x {{ D::fmtReal($sd3,2) }} = {{ $margin3 }}; sehingga prediksi {{ $ma3 }} (range {{ max(0,$ma3-$margin3) }}-{{ $ma3+$margin3 }}).</p>
<div class="doc-rumus"><b>Rumus 5.</b> &nbsp; MA = jumlah(y<sub>jendela</sub>) / window; &nbsp; CI 95% = MA +- 1,96 x SD</div>
<div class="doc-cap">Tabel 13. Langkah moving average + CI ({{ end($tahunList) }})</div>
<table class="doc-table">
<thead><tr><th>Langkah</th><th>Perhitungan</th><th>Hasil</th></tr></thead>
<tbody>
<tr><td>1. Rata-rata</td><td>({{ $tiga[0] }} + {{ $tiga[1] }} + {{ $tiga[2] }}) / 3 = {{ array_sum($tiga) }} / 3</td><td>{{ D::fmtReal(array_sum($tiga)/3,2) }} dibulatkan {{ $ma3 }}</td></tr>
<tr><td>2. SD (sampel)</td><td>Jumlah kuadrat selisih = {{ D::fmtReal(array_sum(array_map(fn($v)=>($v-D::mean($tiga))**2,$tiga)),2) }}; dibagi 2; diakarkan</td><td>SD = {{ D::fmtReal($sd3,2) }}</td></tr>
<tr><td>3. Margin 95%</td><td>1,96 x {{ D::fmtReal($sd3,2) }}</td><td>{{ D::fmtReal(1.96*$sd3,2) }} dibulatkan {{ $margin3 }}</td></tr>
<tr><td>4. Prediksi + CI</td><td>{{ $ma3 }}; bawah = max(0, {{ $ma3 }}-{{ $margin3 }}); atas = {{ $ma3 }}+{{ $margin3 }}</td><td>{{ $ma3 }} (range {{ max(0,$ma3-$margin3) }}-{{ $ma3+$margin3 }})</td></tr>
</tbody>
</table>
<p class="doc-para">Contoh metrik: aktual [100, 120] vs prediksi [110, 115] memberi MAE = (10+5)/2 = 7,5; MAPE = ((0,1)+(0,0417))/2 x 100% = 7,08%; R<sup>2</sup> = 1 - 125/200 = 0,375 (rata-rata aktual 110; SS_tot 200; SS_res 125).</p>
<div class="doc-rumus"><b>Rumus 6.</b> &nbsp; MAE = mean|y - t|; &nbsp; MAPE = mean(|y - t| / t) x 100%; &nbsp; R<sup>2</sup> = 1 - SS<sub>res</sub> / SS<sub>tot</sub></div>
<div class="doc-cap">Tabel 14. Contoh metrik evaluasi (ilustrasi)</div>
<table class="doc-table">
<thead><tr><th>Metrik</th><th>Perhitungan</th><th>Hasil</th></tr></thead>
<tbody>
<tr><td>MAE</td><td>(|100-110| + |120-115|) / 2 = (10 + 5) / 2</td><td>7,5</td></tr>
<tr><td>MAPE</td><td>((10/100) + (5/120)) / 2 x 100%</td><td>7,08%</td></tr>
<tr><td>R<sup>2</sup></td><td>Rata-rata aktual = 110. SS<sub>tot</sub> = 200. SS<sub>res</sub> = 125</td><td>0,375</td></tr>
</tbody>
</table>

@php $mapeStr = $met['mape'] !== null ? D::fmtReal($met['mape'],2).'%' : 'belum terukur'; $r2Disp = D::fmtReal($met['r2_raw'],4); @endphp
<div class="doc-figure"><img src="data:image/svg+xml;base64,{{ base64_encode($figureActualPred) }}" style="width:100%"></div>
<div class="doc-cap">Gambar 4. Perbandingan aktual vs prediksi baseline moving-average murni {{ $evalLabel }} (MAE {{ D::fmtReal($met['mae'],2) }} | MAPE {{ $mapeStr }} | R<sup>2</sup> {{ $r2Disp }})</div>
<p class="doc-para">Catatan: R<sup>2</sup> negatif berarti prediksi baseline lebih buruk daripada sekadar menebak rata-rata; sistem menyimpan versi yang dijepit pada rentang 0-1.</p>
<p class="doc-para">Gambar 4 memakai deret aktual {{ $evalLabel }} ({{ implode(', ', $aktual2024) }}) dan prediksi baseline walk-forward MA(3) murni ({{ implode(', ', $pred2024) }}) dengan metrik aktual vs prediksi tersebut. Polanya analog Gambar 4 penelitian acuan, namun pada data {{ $nBulan }} bulan dengan CI yang sama. Baseline di sini sengaja MA murni (tanpa penyesuaian musiman) sebagai ilustrasi evaluasi yang reproduksibel; fallback moving average produksi menambahkan faktor musiman bulan kalender sebagaimana Tabel 7.</p>
@php $kuadrat = array_map(fn($a,$p)=>($a-$p)**2, $aktual2024, $pred2024); @endphp
<p class="doc-para">Verifikasi manual metrik Gambar 4: selisih absolut per bulan = {{ implode(', ', $met['selisih']) }} dengan jumlah {{ $met['sum_ae'] }} sehingga MAE = {{ $met['sum_ae'] }} / 12 = {{ D::fmtReal($met['mae'],2) }}. Jumlah kuadrat selisih SS<sub>res</sub> = {{ implode('+', $kuadrat) }} = {{ D::fmtReal($met['ss_res'],0) }}; rata-rata aktual = {{ array_sum($aktual2024) }} / 12 = {{ D::fmtReal($met['avg'],2) }} dengan SS<sub>tot</sub> = {{ D::fmtReal($met['ss_tot'],2) }} sehingga R<sup>2</sup> = 1 - {{ D::fmtReal($met['ss_res'],0) }} / {{ D::fmtReal($met['ss_tot'],2) }} = {{ D::fmtReal($met['r2_raw'],4) }}. MAPE = rata-rata {{ $met['n_nonzero'] }} suku |selisih|/aktual (aktual nol dilewati) x 100% = {{ $mapeStr }}.</p>
@if($model0 && $model0['r2'] !== null)
    @if($model0['r2'] < 0.30)
        <p class="doc-para">Model ANN tersimpan untuk {{ $nama0 }} berstatus '{{ $model0['status'] }}' dengan R<sup>2</sup> {{ D::fmtReal($model0['r2'],4) }}, MAE {{ D::fmtReal($model0['mae'] ?? 0,2) }}, dan MAPE {{ D::fmtReal($model0['mape'] ?? 0,2) }}% (training {{ $model0['tanggal_training'] }}). Karena R<sup>2</sup> di bawah ambang 0,30, sistem memakai fallback moving average sesuai Tabel 7; evaluasi pada Gambar 4 memakai prediksi baseline tersebut.</p>
    @else
        <p class="doc-para">Model ANN tersimpan untuk {{ $nama0 }} berstatus '{{ $model0['status'] }}' dengan R<sup>2</sup> {{ D::fmtReal($model0['r2'],4) }} (training {{ $model0['tanggal_training'] }}); evaluasi baseline moving-average pada Gambar 4 disajikan sebagai pembanding metode.</p>
    @endif
@else
    <p class="doc-para">Belum ada metrik model ANN tersimpan yang terukur untuk {{ $nama0 }}; evaluasi pada Gambar 4 memakai prediksi baseline moving average sesuai Tabel 7.</p>
@endif

@php $thnFc = end($tahunList)+1; @endphp
<p class="doc-para">Mekanisme autoregresif: prediksi bulan ke-1 memakai seluruh lag aktual ({{ $desLabel }} = {{ $tiga[2] }}) menghasilkan {{ $fc[0] }}; bulan ke-2 memakai lag_1 = {{ $fc[0] }} menghasilkan {{ $fc[1] }}; bulan ke-3 memakai lag_1 = {{ $fc[1] }} dan lag_2 = {{ $fc[0] }} menghasilkan {{ $fc[2] }}.</p>
<div class="doc-cap">Tabel 15. Rollout autoregresif 3 bulan</div>
<table class="doc-table">
<thead><tr><th>Bulan</th><th>Sumber lag_1</th><th>Hasil</th></tr></thead>
<tbody>
<tr><td>Bulan ke-1</td><td>Data aktual {{ $desLabel }} ({{ $tiga[2] }})</td><td>{{ $fc[0] }}</td></tr>
<tr><td>Bulan ke-2</td><td>Prediksi bulan ke-1 ({{ $fc[0] }})</td><td>{{ $fc[1] }}</td></tr>
<tr><td>Bulan ke-3</td><td>Prediksi bulan ke-2 ({{ $fc[1] }})</td><td>{{ $fc[2] }}</td></tr>
</tbody>
</table>
@php $rata6 = D::mean(array_slice($seri,-6)); $rata12 = D::mean(array_slice($seri,-12)); $slope3 = ($tiga[2]-$tiga[0])/2; @endphp
<p class="doc-para">Perhitungan manual vektor 9 fitur untuk prediksi Januari {{ $thnFc }} dari deret {{ end($tahunList) }}: lag_1 = {{ $tiga[2] }}, lag_2 = {{ $tiga[1] }}, lag_3 = {{ $tiga[0] }}; rata-rata 6 bulan = ({{ implode('+', array_slice($seri,-6)) }})/6 = {{ array_sum(array_slice($seri,-6)) }}/6 = {{ D::fmtAuto($rata6) }}; rata-rata 12 bulan = {{ array_sum(array_slice($seri,-12)) }}/12 = {{ D::fmtAuto($rata12) }}; bulan = 1; trend_3_bulan = slope [{{ $tiga[0] }}, {{ $tiga[1] }}, {{ $tiga[2] }}] = ({{ $tiga[2] }}-{{ $tiga[0] }})/2 = {{ D::fmtAuto($slope3) }}; stok_saat_ini = {{ $stok0 }}; tipe_faskes = {{ $tipeFaskesNilai }} ({{ $data['faskes']['tipe'] }}). Vektor [{{ $tiga[2] }}, {{ $tiga[1] }}, {{ $tiga[0] }}, {{ D::fmtAuto($rata6) }}, {{ D::fmtAuto($rata12) }}, 1, {{ D::fmtAuto($slope3) }}, {{ $stok0 }}, {{ $tipeFaskesNilai }}] inilah yang dinormalisasi lalu diumpankan ke ANN.</p>
<div class="doc-figure"><img src="data:image/svg+xml;base64,{{ base64_encode($figureForecast) }}" style="width:100%"></div>
<div class="doc-cap">Gambar 5. Prediksi 3 bulan ke depan dengan CI 95% ({{ $namaFc[0] }} {{ $fc[0] }} [{{ $fcLo[0] }}-{{ $fcHi[0] }}], {{ $namaFc[1] }} {{ $fc[1] }} [{{ $fcLo[1] }}-{{ $fcHi[1] }}], {{ $namaFc[2] }} {{ $fc[2] }} [{{ $fcLo[2] }}-{{ $fcHi[2] }}])</div>
<p class="doc-para">Gambar 5 memvisualkan Tabel 15 dengan whisker CI 95% (+-1,96 SD sampel historis {{ D::fmtReal($sdHist,2) }}; margin = 1,96 x {{ D::fmtReal($sdHist,2) }} = {{ D::fmtReal(1.96*$sdHist,2) }} dibulatkan {{ $marginFc }}); setara Tabel 8 / Gambar 5 penelitian acuan yang menampilkan prediksi tahunan, namun di sini berupa rollout 3 bulan {{ $namaFc[0] }}-{{ $namaFc[2] }}.</p>

@php
$rekom500 = max(0, (int)ceil($butuh - 500));
$cov500 = $rataBln ? (500 / $rataBln) * 30.44 : 0;
$st500 = $rekom500 <= 0 ? 'Aman' : ($cov500 < 21 ? 'Kritis' : 'Perlu Pesan');
$rekom30 = max(0, (int)ceil($butuh - 30));
$cov30 = $rataBln ? (30 / $rataBln) * 30.44 : 0;
$st30 = $rekom30 <= 0 ? 'Aman' : ($cov30 < 21 ? 'Kritis' : 'Perlu Pesan');
@endphp
<p class="doc-para">Hasil prediksi diubah menjadi rekomendasi pengadaan dengan Rumus 7. Contoh dari Gambar 5: total prediksi 3 bulan T = {{ implode('+',$fc) }} = {{ $totalT }} unit dan stok S = {{ $stok0 }} unit memberi ceil({{ D::fmtReal($butuh,2) }} - {{ $stok0 }}) = ceil({{ D::fmtReal($butuh-$stok0,2) }}) = {{ $rekom }} unit; coverage ({{ $stok0 }} / {{ D::fmtReal($rataBln,2) }}) x 30,44 = {{ D::fmtReal($coverage,1) }} hari sehingga berstatus {{ $statusStok }}. Pembanding: stok 500 memberi rekom {{ $rekom500 }} ({{ $st500 }}, {{ D::fmtReal($butuh,2) }}-500 {{ $butuh - 500 >= 0 ? '>' : '<' }} 0); stok 30 memberi rekom {{ $rekom30 }} dengan coverage {{ D::fmtReal($cov30,2) }} hari {{ $cov30 < 21 ? '<' : '>=' }} 21 hari ({{ $st30 }}).</p>
<div class="doc-rumus"><b>Rumus 7.</b> &nbsp; rekom = max(0, ceil(T x 1,20 - S))</div>
<div class="doc-cap">Tabel 16. Contoh rekomendasi pengadaan (T={{ $totalT }} dari Gambar 5)</div>
<table class="doc-table">
<thead><tr><th>Langkah</th><th>Perhitungan</th><th>Hasil</th></tr></thead>
<tbody>
<tr><td>1. Kebutuhan + safety 20%</td><td>{{ $totalT }} x 1,20</td><td>{{ D::fmtReal($butuh,2) }}</td></tr>
<tr><td>2. Rekomendasi</td><td>ceil({{ D::fmtReal($butuh,2) }} - {{ $stok0 }}), min 0</td><td>{{ $rekom }} unit</td></tr>
<tr><td>3. Rata-rata bulanan</td><td>{{ $totalT }} / 3</td><td>{{ D::fmtReal($rataBln,2) }} unit/bulan</td></tr>
<tr><td>4. Coverage</td><td>({{ $stok0 }} / {{ D::fmtReal($rataBln,2) }}) x 30,44 hari</td><td>{{ D::fmtReal($coverage,1) }} hari</td></tr>
<tr><td>5. Status</td><td>rekom &gt; 0 dan coverage &gt;= 21 hari</td><td>{{ $statusStok }}</td></tr>
</tbody>
</table>
@php $batas = $totalT < $capCap ? 'jauh di bawah' : 'melampaui'; $dibatasi = $totalT <= $capCap ? 'tidak dibatasi' : 'dibatasi ke cap'; @endphp
<p class="doc-para">Pengaman cap mencegah ledakan autoregresif: dari deret {{ $nBulan }} bulan {{ $nama0 }} (histMax = {{ $histMax }}, histMean = {{ D::fmtReal($histMean,2) }}) diperoleh cap = max({{ $histMax }} x 3; {{ D::fmtReal($histMean,2) }} x 5; 50) = max({{ D::fmtReal($histMax*3,2) }}; {{ D::fmtReal($histMean*5,2) }}; 50) = {{ $capCap }} (ceil). Total T = {{ $totalT }} {{ $batas }} {{ $capCap }} sehingga {{ $dibatasi }}; bila keluaran ANN mentah melampaui cap, nilainya dipotong ke cap sebelum CI dihitung.</p>
<div class="doc-rumus"><b>Rumus 8.</b> &nbsp; cap = max(histMax x 3, histMean x 5, 50)</div>
<p class="doc-para">Uji kombinasi parameter berikut adalah ilustrasi grid-search mengikuti gaya Tabel 5 penelitian acuan, bukan hasil eksperimen yang dijalankan. Konfigurasi aktual sistem tetap: learning rate 0,01; epoch 800; L2 1e-4; patience 20.</p>
<div class="doc-cap">Tabel 17. Ilustrasi pengujian parameter</div>
<table class="doc-table">
<thead><tr><th>Learning rate</th><th>Epoch</th><th>L2</th><th>Perilaku ilustratif</th></tr></thead>
<tbody>
<tr><td>0,005</td><td>800</td><td>1e-4</td><td>Konvergen lambat, butuh epoch lebih banyak</td></tr>
<tr><td>0,01</td><td>800</td><td>1e-4</td><td>Stabil - konfigurasi aktif sistem</td></tr>
<tr><td>0,05</td><td>800</td><td>1e-4</td><td>Berisiko osilasi, loss tidak turun mulus</td></tr>
<tr><td>0,01</td><td>400</td><td>1e-4</td><td>Berhenti prematur, akurasi di bawah optimal</td></tr>
<tr><td>0,01</td><td>800</td><td>1e-3</td><td>Regularisasi terlalu kuat, model kurang peka</td></tr>
</tbody>
</table>
<p class="doc-para">Pemanfaatan hasil: RKO terisi otomatis (keterangan Prediksi AI: jumlah + range, atau tombol Generate dari Prediksi untuk semua obat aktif dengan penanda kolom AI; rumus Kemenkes tetap berlaku) dan petugas faskes dapat membuat permintaan obat langsung dari halaman Prediksi AI. Hak akses pada Tabel 18; pengoperasian via php artisan ai:train-models (opsi --fasilitas-id, --obat-id, --force), menu Prediksi AI, dan agregat Dashboard AI. Pengujian otomatis: MovingAverageServiceTest (7 test) dan AiTrainModelsCommandTest (2 test).</p>

<div class="doc-cap">Tabel 18. Hak akses fitur prediksi</div>
<table class="doc-table">
<thead><tr><th>Permission</th><th>Super Admin</th><th>Gudang</th><th>Dinas</th><th>Puskes. / Pustu</th></tr></thead>
<tbody>
<tr><td>view_model_prediksi</td><td>Ya</td><td>Ya</td><td>Ya</td><td>Ya / Tidak</td></tr>
<tr><td>view_prediksi_kebutuhan</td><td>Ya</td><td>Ya</td><td>Ya</td><td>Ya / Ya</td></tr>
<tr><td>create/update/delete</td><td>Ya</td><td>Tidak</td><td>Tidak</td><td>Tidak</td></tr>
</tbody>
</table>
<p class="doc-para">Dengan cara yang sama, prediksi dilakukan untuk dua obat sampel lain ({{ $nama1 }} dan {{ $nama2 }}). Ringkasan ketiga obat mengikuti pola Tabel 7 dan Tabel 8 penelitian acuan, namun dalam metrik mentah sistem (R<sup>2</sup>/MAE/MAPE) dan horizon 3 bulan; seluruh nilai dihitung walk-forward dari deret aktual masing-masing, bukan log training riil. Baris {{ $nama0 }} memakai nilai dari Gambar 4 dan Gambar 5 di atas.</p>
<div class="doc-cap">Tabel 19. Ringkasan evaluasi baseline 3 obat sampel (aktual vs prediksi {{ $evalLabel }})</div>
<table class="doc-table">
<thead><tr><th>Obat sampel</th><th>MAE</th><th>MAPE</th><th>R<sup>2</sup></th><th>Akurasi</th></tr></thead>
<tbody>
@foreach($ringkas as $r)
<tr><td>{{ $r['info']['nama'] }}</td><td>{{ D::fmtReal($r['met']['mae'],2) }}</td><td>{{ $r['met']['mape'] !== null ? D::fmtReal($r['met']['mape'],2).'%' : '-' }}</td><td>{{ D::fmtReal($r['met']['r2_raw'],4) }}</td><td>{{ $r['met']['mape'] !== null ? D::fmtReal(100 - $r['met']['mape'],2).'%' : '-' }}</td></tr>
@endforeach
</tbody>
</table>
<p class="doc-para">Catatan: Akurasi = 100% - MAPE mengikuti definisi penelitian acuan; sistem menyimpan metrik mentah R<sup>2</sup>/MAE/MAPE.</p>
<div class="doc-cap">Tabel 20. Prediksi 3 bulan per obat sampel (unit + CI 95%)</div>
<table class="doc-table">
<thead><tr><th>Obat sampel</th><th>{{ $namaFc[0] }}</th><th>{{ $namaFc[1] }}</th><th>{{ $namaFc[2] }}</th></tr></thead>
<tbody>
@foreach($ringkas as $r)
<tr><td>{{ $r['info']['nama'] }}</td><td>{{ $r['fc'][0] }} [{{ $r['lo'][0] }}-{{ $r['hi'][0] }}]</td><td>{{ $r['fc'][1] }} [{{ $r['lo'][1] }}-{{ $r['hi'][1] }}]</td><td>{{ $r['fc'][2] }} [{{ $r['lo'][2] }}-{{ $r['hi'][2] }}]</td></tr>
@endforeach
</tbody>
</table>

<h2 class="doc-h2">4. Kesimpulan</h2>
<p class="doc-para">Fitur prediksi kebutuhan obat menerapkan Jaringan Saraf Tiruan MLP 9-12-8-1 yang ditulis murni dengan PHP: data pemakaian {{ $faskesNama }} diagregasi bulanan, direkayasa menjadi 9 fitur lag/tren/stok, dinormalisasi z-score, dilatih dengan SGD + early stopping, dievaluasi dengan R<sup>2</sup>/MAE/MAPE, lalu dipakai memprediksi 3 bulan ke depan secara autoregresif dengan fallback moving average berpenguatan musiman bila data belum cukup atau R<sup>2</sup> di bawah 0,30. Obat sampel utama adalah {{ $nama0 }}; {{ $nama1 }} dan {{ $nama2 }} diproses identik dengan ringkasan pada Tabel 19 dan Tabel 20.</p>
<p class="doc-para">Dibanding penelitian acuan (BPNN 12-12-1, min-max, MATLAB, keluaran tabel tahunan), sistem ini memakai fitur rekayasa yang lebih kaya, fungsi aktivasi ReLU, split 80/20, penyimpanan model ganda (JSON + file), serta integrasi langsung ke RKO dan permintaan obat sehingga hasil prediksi dapat dieksekusi menjadi perencanaan.</p>
<p class="doc-para">Batasan yang perlu dicatat: komposisi lapis tersembunyi 12-8 adalah ketetapan konfigurasi (bukan hasil trial-and-error); model membutuhkan minimal 6 bulan berisi agar ANN dipakai; sistem menyimpan metrik mentah (kolom Akurasi pada Tabel 19 dihitung sebagai 100% - MAPE hanya untuk perbandingan dengan penelitian acuan). Data dummy yang acak praktis tidak dapat diprediksi sehingga R<sup>2</sup> sekitar nol adalah wajar; angka-angka Bab 3 berfungsi sebagai demonstrasi metode, bukan klaim akurasi.</p>
<p class="doc-para">Saran pengembangan: uji variasi hiperparameter secara sistematis, penjadwalan retraining adaptif berbasis drift akurasi, serta fitur musiman spesifik-per-obat (mis. kelompok hujan/kemarau/program per obat) dan validasi silang musiman.</p>

<h2 class="doc-h2">Daftar Rujukan</h2>
<p class="doc-ref">[1] &nbsp; F. Khairati dan H. Putra, Prediksi Kuantitas Penggunaan Obat pada Layanan Kesehatan Menggunakan Algoritma Backpropagation Neural Network, Jurnal Sistim Informasi dan Teknologi, vol. 4, no. 3, hal. 128-135, 2022, doi: 10.37034/jsisfotek.v4i3.158.</p>

</body>
</html>
