<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obat', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('kode_obat');
            $table->string('nama_obat');
            $table->string('nama_generik')->nullable();
            $table->string('kategori');
            $table->char('ven_kategori', 1)->nullable();
            $table->string('satuan');
            $table->string('kekuatan')->nullable();
            $table->enum('bentuk_sediaan', ['tablet', 'kapsul', 'sirup', 'salep', 'injeksi', 'drop', 'inhaler', 'suppositoria']);
            $table->string('produsen')->nullable();
            $table->string('kemasan')->nullable();
            $table->decimal('harga_satuan', 12, 2)->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->string('metode_stok', 4)->default('fefo');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['kategori', 'status'], 'obat_kategori_status_index');
            $table->unique(['kode_obat'], 'obat_kode_obat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obat');
    }
};
