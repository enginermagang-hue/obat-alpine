<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumber_dana', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode');
            $table->string('nama');
            $table->integer('tahun');
            $table->decimal('total_anggaran', 14, 2);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('keterangan')->nullable();
            $table->unique(['kode', 'tahun'], 'sumber_dana_kode_tahun_unique');
            $table->index(['status'], 'sumber_dana_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumber_dana');
    }
};
