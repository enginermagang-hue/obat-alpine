<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutup_buku', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->integer('tahun');
            $table->enum('tipe', ['inisial', 'tahunan']);
            $table->date('cutoff_at');
            $table->dateTime('deadline_at')->nullable();
            $table->unsignedBigInteger('neraca_id')->nullable();
            $table->enum('status', ['draft', 'tutup', 'dibuka_kembali'])->default('tutup');
            $table->unsignedBigInteger('ditutup_oleh')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['fasilitas_id', 'tahun'], 'tutup_buku_fasilitas_id_tahun_unique');
            $table->index(['tahun', 'status'], 'tutup_buku_tahun_status_index');
            $table->index(['tipe', 'status'], 'tutup_buku_tipe_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutup_buku');
    }
};
