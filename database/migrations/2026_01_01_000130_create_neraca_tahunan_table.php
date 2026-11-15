<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neraca_tahunan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_neraca', 100);
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->integer('tahun');
            $table->enum('status', ['draft', 'selesai'])->default('draft');
            $table->text('catatan')->nullable();
            $table->unsignedBigInteger('dibuat_oleh');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['fasilitas_id', 'tahun'], 'neraca_tahunan_fasilitas_id_tahun_index');
            $table->unique(['nomor_neraca'], 'neraca_tahunan_nomor_neraca_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('neraca_tahunan');
    }
};
