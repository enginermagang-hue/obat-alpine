<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemakaian_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_pemakaian', 50)->nullable();
            $table->unsignedBigInteger('fasilitas_id');
            $table->date('tanggal_pemakaian');
            $table->enum('jenis_pelayanan', ['rawat_jalan', 'rawat_inap', 'uks', 'posyandu', 'pusling', 'gigi', 'laboratorium', 'apotek', 'lainnya']);
            $table->string('nama_pasien')->nullable();
            $table->string('no_rekam_medis', 50)->nullable();
            $table->string('diagnosa_kode')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['fasilitas_id', 'tanggal_pemakaian', 'jenis_pelayanan'], 'idx_pemakaian_faskes_obat');
            $table->unique(['nomor_pemakaian'], 'pemakaian_obat_nomor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemakaian_obat');
    }
};
