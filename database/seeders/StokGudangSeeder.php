<?php

namespace Database\Seeders;

use App\Models\Obat;
use App\Models\StokGudang;
use Illuminate\Database\Seeder;

class StokGudangSeeder extends Seeder
{
    /**
     * Stok awal gudang (Dinas) per 1 Juni 2024.
     * Jumlah mencakup kebutuhan 6 puskesmas untuk ~3-4 bulan
     * pada volume pemakaian yang sudah diskalakan (SKALA_KONSUMSI).
     * Tier mikro/topikal/sirup disize ≥ 6 faskes × kebutuhan awal per
     * faskes agar sweep distribusi awal tidak menghabiskan gudang
     * sebelum faskes terakhir terlayani.
     */
    public function run(): void
    {
        $obatList = Obat::all();

        $stokAwalMap = $this->getInitialStockMap();

        foreach ($obatList as $obat) {
            $stok = $stokAwalMap[$obat->kode_obat] ?? $stokAwalMap['default'];

            StokGudang::firstOrCreate(
                ['obat_id' => $obat->id],
                [
                    'jumlah' => $stok['jumlah'],
                    'stok_minimum' => $stok['stok_minimum'],
                ],
            );
        }
    }

    /**
     * @return array<string, array{jumlah: int, stok_minimum: int}>
     */
    private function getInitialStockMap(): array
    {
        return [
            // === Volume Tinggi ===
            '92001267' => ['jumlah' => 36_000, 'stok_minimum' => 6_000], // Parasetamol 500mg
            '92000881' => ['jumlah' => 24_000, 'stok_minimum' => 4_500],  // Amoxicillin 500mg
            '92000407' => ['jumlah' => 15_000, 'stok_minimum' => 3_000],  // Amlodipin 10mg
            '92001058' => ['jumlah' => 15_000, 'stok_minimum' => 3_000],  // Amlodipin 5mg
            '92000209' => ['jumlah' => 15_000, 'stok_minimum' => 3_000],  // Metformin 500mg
            '92000344' => ['jumlah' => 12_000, 'stok_minimum' => 2_400],  // Captopril 25mg
            '92000517' => ['jumlah' => 24_000, 'stok_minimum' => 4_500],  // Vitamin C 50mg
            '92000129' => ['jumlah' => 12_000, 'stok_minimum' => 2_400],  // Vitamin C 250mg
            '92000798' => ['jumlah' => 9_000, 'stok_minimum' => 1_800],   // Antasida Tablet
            '92001081' => ['jumlah' => 12_000, 'stok_minimum' => 2_400],  // Ibuprofen 400mg
            '92001386' => ['jumlah' => 9_000, 'stok_minimum' => 1_800],   // Kotrimoksazol

            // === Volume Sedang ===
            '92000146' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Albendazole 400mg
            '92000406' => ['jumlah' => 1_800, 'stok_minimum' => 300],     // Amoxicillin Sirup 250mg/5mL
            '92000486' => ['jumlah' => 1_500, 'stok_minimum' => 300],     // Amoxicillin Sirup 125mg/5mL
            '92000234' => ['jumlah' => 1_200, 'stok_minimum' => 240],     // Amoxicillin Sirup 100mg/5mL
            '92000185' => ['jumlah' => 1_500, 'stok_minimum' => 300],     // Ibuprofen Suspensi
            '92002848' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Oralit
            '92000163' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Glimepirid 2mg
            '92000914' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Furosemid 40mg
            '92003720' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Ranitidin 150mg
            '92001960' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Omeprazole 20mg
            '92000860' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Salbutamol 2mg
            '92000535' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Diclofenac 50mg
            '92000366' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Simvastatin 10mg
            '92000706' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Prednison 5mg
            '92001124' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Kalsium Laktat
            '92000201' => ['jumlah' => 6_000, 'stok_minimum' => 1_200],   // Zinc Sulfate
            '92000124' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Alopurinol 300mg
            '92000281' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Alopurinol 100mg
            '92000653' => ['jumlah' => 9_000, 'stok_minimum' => 1_500],   // Fe + Folic (tablet tambah darah)
            '92001885' => ['jumlah' => 4_500, 'stok_minimum' => 900],     // Pyridoxin 25mg

            // === Volume Rendah (Spesifik/Kronis) ===
            '92000715' => ['jumlah' => 9_000, 'stok_minimum' => 1_500],   // OAT FDC Kat 1 (TB)
            '92000564' => ['jumlah' => 4_500, 'stok_minimum' => 900],     // Tenofovir/Lamivudin/Efavirenz (ARV)
            '92000472' => ['jumlah' => 4_500, 'stok_minimum' => 900],     // Asiklovir 200mg
            '92000770' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Asiklovir 400mg
            '92000347' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Primakuin 25mg

            // === Topikal/Salep ===
            '92000403' => ['jumlah' => 1_800, 'stok_minimum' => 360],       // Mikonazol Krim
            '92000572' => ['jumlah' => 1_800, 'stok_minimum' => 360],       // Betametason Krim
            '92000731' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Gentamisin Salep Mata
            '92001095' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Asiklovir Krim

            // === Injeksi (Volume Rendah) ===
            '92000634' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Epinephrine
            '92000756' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Oksitosin
            '92000986' => ['jumlah' => 1_800, 'stok_minimum' => 360],       // Lidocain

            // === Obat Psikotropika ===
            '92000435' => ['jumlah' => 1_500, 'stok_minimum' => 300],     // Diazepam 5mg
            '92000445' => ['jumlah' => 1_800, 'stok_minimum' => 360],       // Haloperidol 5mg

            // === Vitamin Khusus ===
            '92000809' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Vitamin A 200.000 IU

            // === Sirup Rendah ===
            '92000637' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Albendazole Susp 200mg/5mL
            '92002856' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Albendazole Susp 400mg/5mL
            '92000139' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Domperidon 10mg
            '92001205' => ['jumlah' => 1_200, 'stok_minimum' => 240],       // Domperidon Susp
            '92002094' => ['jumlah' => 1_800, 'stok_minimum' => 360],       // Antasida Suspensi
            '92000465' => ['jumlah' => 1_500, 'stok_minimum' => 300],     // Fitomenadion 10mg
            '91000255' => ['jumlah' => 3_000, 'stok_minimum' => 600],     // Loratadin 10mg

            'default' => ['jumlah' => 1_500, 'stok_minimum' => 300],
        ];
    }
}
