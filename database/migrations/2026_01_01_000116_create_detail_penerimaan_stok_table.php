<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_penerimaan_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('penerimaan_id');
            $table->unsignedBigInteger('obat_id');
            $table->string('batch_number', 100)->nullable();
            $table->date('tanggal_expired');
            $table->integer('jumlah')->default(0);
            $table->decimal('harga_satuan', 12, 2)->nullable();
            $table->decimal('sub_total', 12, 2)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_penerimaan_stok');
    }
};
