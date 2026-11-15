<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fasilitas_kesehatan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_faskes');
            $table->string('nama');
            $table->enum('tipe', ['puskesmas', 'pustu']);
            $table->unsignedBigInteger('puskesmas_induk_id')->nullable();
            $table->text('alamat');
            $table->string('pic')->nullable();
            $table->string('kontak_pic')->nullable();
            $table->string('telepon')->nullable();
            $table->string('kepala_faskes')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['kode_faskes'], 'fasilitas_kesehatan_kode_faskes_unique');
            $table->index(['tipe', 'status'], 'fasilitas_kesehatan_tipe_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fasilitas_kesehatan');
    }
};
