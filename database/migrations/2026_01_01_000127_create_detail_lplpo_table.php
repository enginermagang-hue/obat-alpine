<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_lplpo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lplpo_id');
            $table->unsignedBigInteger('obat_id');
            $table->integer('stok_awal');
            $table->integer('jumlah_masuk');
            $table->integer('jumlah_keluar');
            $table->integer('sisa_stok');
            $table->integer('stok_optimum')->nullable();
            $table->integer('permintaan_selanjutnya');
            $table->boolean('sudah_diminta')->default(0);
            $table->unsignedBigInteger('permintaan_id')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_lplpo');
    }
};
