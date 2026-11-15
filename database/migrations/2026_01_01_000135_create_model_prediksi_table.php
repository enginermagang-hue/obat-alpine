<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_prediksi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id');
            $table->unsignedBigInteger('obat_id');
            $table->longText('model_data');
            $table->string('model_path')->nullable();
            $table->decimal('akurasi_r2', 5, 4)->nullable();
            $table->decimal('mae', 10, 2)->nullable();
            $table->decimal('mape', 5, 2)->nullable();
            $table->date('tanggal_training');
            $table->integer('data_training_count');
            $table->json('fitur_digunakan')->nullable();
            $table->enum('status', ['aktif', 'kadaluarsa', 'gagal', 'data_belum_cukup'])->default('data_belum_cukup');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['fasilitas_id', 'obat_id'], 'model_prediksi_fasilitas_id_obat_id_unique');
            $table->index(['status', 'tanggal_training'], 'model_prediksi_status_tanggal_training_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_prediksi');
    }
};
