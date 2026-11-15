<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->unsignedBigInteger('obat_id');
            $table->enum('tipe', ['masuk', 'keluar', 'distribusi_masuk', 'distribusi_keluar', 'rusak', 'hilang', 'expired', 'opname', 'penyesuaian']);
            $table->integer('jumlah');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->string('referensi_type')->nullable();
            $table->unsignedBigInteger('referensi_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->text('keterangan')->nullable();
            $table->date('tanggal');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['obat_id', 'fasilitas_id', 'tipe', 'tanggal'], 'riwayat_stok_obat_id_fasilitas_id_tipe_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_stok');
    }
};
