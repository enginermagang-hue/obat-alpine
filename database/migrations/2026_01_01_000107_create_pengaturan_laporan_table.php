<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_laporan', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('fasilitas_id')->nullable();
            $table->enum('grup', ['kop_surat', 'tanda_tangan', 'identitas_laporan', 'default_laporan', 'pdf', 'format_nomor', 'rko']);
            $table->string('key');
            $table->text('value');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['fasilitas_id', 'grup', 'key'], 'pengaturan_laporan_fasilitas_id_grup_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_laporan');
    }
};
