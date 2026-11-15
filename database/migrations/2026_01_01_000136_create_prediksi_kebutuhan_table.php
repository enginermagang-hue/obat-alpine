<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prediksi_kebutuhan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->integer('periode_bulan');
            $table->integer('periode_tahun');
            $table->integer('jumlah_prediksi');
            $table->integer('confidence_lower')->nullable();
            $table->integer('confidence_upper')->nullable();
            $table->enum('metode', ['ai_gradient_boost', 'ai_random_forest', 'moving_average', 'manual', 'ann_php'])->nullable();
            $table->unsignedBigInteger('dibuat_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['metode'], 'prediksi_kebutuhan_metode_index');
            $table->unique(['fasilitas_id', 'obat_id', 'periode_bulan', 'periode_tahun'], 'uq_prediksi_faskes_obat_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prediksi_kebutuhan');
    }
};
