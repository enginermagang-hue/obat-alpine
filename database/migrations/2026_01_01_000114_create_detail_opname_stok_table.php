<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_opname_stok', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('opname_id');
            $table->unsignedBigInteger('obat_id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->integer('stok_sistem');
            $table->integer('stok_fisik');
            $table->integer('selisih');
            $table->string('batch_number', 100)->nullable();
            $table->date('tanggal_expired')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_opname_stok');
    }
};
