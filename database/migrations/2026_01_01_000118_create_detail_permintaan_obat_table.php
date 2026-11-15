<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_permintaan_obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('permintaan_id');
            $table->unsignedBigInteger('obat_id');
            $table->integer('jumlah_diminta');
            $table->integer('jumlah_disetujui')->nullable();
            $table->integer('jumlah_dikirim')->nullable();
            $table->integer('jumlah_diterima')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_permintaan_obat');
    }
};
