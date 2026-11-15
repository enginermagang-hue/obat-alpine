<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_dana_penggunaan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('sumber_dana_id');
            $table->unsignedBigInteger('rko_id')->nullable();
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->enum('tipe', ['perencanaan', 'realisasi', 'alokasi']);
            $table->integer('jumlah_obat');
            $table->decimal('total_biaya', 14, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['sumber_dana_id', 'tipe', 'tanggal'], 'sumber_dana_penggunaan_sumber_dana_id_tipe_tanggal_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumber_dana_penggunaan');
    }
};
