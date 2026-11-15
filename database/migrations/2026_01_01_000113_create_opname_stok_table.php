<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opname_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_opname');
            $table->enum('tipe', ['penyesuaian', 'stok_awal', 'stok_baru'])->nullable()->default('penyesuaian');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->date('tanggal_opname');
            $table->enum('status', ['draft', 'proses', 'selesai'])->default('draft');
            $table->unsignedBigInteger('user_id');
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['fasilitas_id', 'tanggal_opname'], 'opname_stok_fasilitas_id_tanggal_opname_index');
            $table->unique(['nomor_opname'], 'opname_stok_nomor_opname_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_stok');
    }
};
