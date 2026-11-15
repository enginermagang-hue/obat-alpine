<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_stok', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'batch_stok_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('obat_id', 'batch_stok_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('penerimaan_id', 'batch_stok_penerimaan_id_foreign')
                ->references('id')
                ->on('penerimaan_stok')
                ->onDelete('set null');
            $table->foreign('sumber_dana_id', 'batch_stok_sumber_dana_id_foreign')
                ->references('id')
                ->on('sumber_dana')
                ->onDelete('set null');
        });

        Schema::table('detail_distribusi_obat', function (Blueprint $table) {
            $table->foreign('batch_id', 'detail_distribusi_obat_batch_id_foreign')
                ->references('id')
                ->on('batch_stok')
                ->onDelete('cascade');
            $table->foreign('distribusi_id', 'detail_distribusi_obat_distribusi_id_foreign')
                ->references('id')
                ->on('distribusi_obat')
                ->onDelete('cascade');
            $table->foreign('obat_id', 'detail_distribusi_obat_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('detail_lplpo', function (Blueprint $table) {
            $table->foreign('lplpo_id', 'detail_lplpo_lplpo_id_foreign')
                ->references('id')
                ->on('laporan_lplpo')
                ->onDelete('cascade');
            $table->foreign('obat_id', 'detail_lplpo_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('permintaan_id', 'detail_lplpo_permintaan_id_foreign')
                ->references('id')
                ->on('permintaan_obat')
                ->onDelete('set null');
        });

        Schema::table('detail_neraca_sumber_dana', function (Blueprint $table) {
            $table->foreign('detail_neraca_id', 'detail_neraca_sumber_dana_detail_neraca_id_foreign')
                ->references('id')
                ->on('detail_neraca_tahunan')
                ->onDelete('cascade');
            $table->foreign('sumber_dana_id', 'detail_neraca_sumber_dana_sumber_dana_id_foreign')
                ->references('id')
                ->on('sumber_dana')
                ->onDelete('cascade');
        });

        Schema::table('detail_neraca_tahunan', function (Blueprint $table) {
            $table->foreign('neraca_id', 'detail_neraca_tahunan_neraca_id_foreign')
                ->references('id')
                ->on('neraca_tahunan')
                ->onDelete('cascade');
            $table->foreign('obat_id', 'detail_neraca_tahunan_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('detail_opname_stok', function (Blueprint $table) {
            $table->foreign('batch_id', 'detail_opname_stok_batch_id_foreign')
                ->references('id')
                ->on('batch_stok')
                ->onDelete('set null');
            $table->foreign('obat_id', 'detail_opname_stok_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('opname_id', 'detail_opname_stok_opname_id_foreign')
                ->references('id')
                ->on('opname_stok')
                ->onDelete('cascade');
        });

        Schema::table('detail_pemakaian_obat', function (Blueprint $table) {
            $table->foreign('batch_id', 'detail_pemakaian_obat_batch_id_foreign')
                ->references('id')
                ->on('batch_stok')
                ->onDelete('set null');
            $table->foreign('obat_id', 'detail_pemakaian_obat_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('restrict');
            $table->foreign('pemakaian_id', 'detail_pemakaian_obat_pemakaian_id_foreign')
                ->references('id')
                ->on('pemakaian_obat')
                ->onDelete('cascade');
        });

        Schema::table('detail_penerimaan_stok', function (Blueprint $table) {
            $table->foreign('obat_id', 'detail_penerimaan_stok_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('penerimaan_id', 'detail_penerimaan_stok_penerimaan_id_foreign')
                ->references('id')
                ->on('penerimaan_stok')
                ->onDelete('cascade');
        });

        Schema::table('detail_permintaan_obat', function (Blueprint $table) {
            $table->foreign('obat_id', 'detail_permintaan_obat_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('permintaan_id', 'detail_permintaan_obat_permintaan_id_foreign')
                ->references('id')
                ->on('permintaan_obat')
                ->onDelete('cascade');
        });

        Schema::table('detail_retur_obat', function (Blueprint $table) {
            $table->foreign('batch_id', 'detail_retur_obat_batch_id_foreign')
                ->references('id')
                ->on('batch_stok')
                ->onDelete('set null');
            $table->foreign('obat_id', 'detail_retur_obat_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('retur_id', 'detail_retur_obat_retur_id_foreign')
                ->references('id')
                ->on('retur_obat')
                ->onDelete('cascade');
        });

        Schema::table('detail_rko', function (Blueprint $table) {
            $table->foreign('lplpo_referensi_id', 'detail_rko_lplpo_referensi_id_foreign')
                ->references('id')
                ->on('laporan_lplpo')
                ->onDelete('set null');
            $table->foreign('obat_id', 'detail_rko_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('prediksi_id', 'detail_rko_prediksi_id_foreign')
                ->references('id')
                ->on('prediksi_kebutuhan')
                ->onDelete('set null');
            $table->foreign('rko_id', 'detail_rko_rko_id_foreign')
                ->references('id')
                ->on('laporan_rko')
                ->onDelete('cascade');
        });

        Schema::table('distribusi_obat', function (Blueprint $table) {
            $table->foreign('fasilitas_penerima_id', 'distribusi_obat_fasilitas_penerima_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('fasilitas_pengirim_id', 'distribusi_obat_fasilitas_pengirim_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('penerima_id', 'distribusi_obat_penerima_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('penerimaan_stok_id', 'distribusi_obat_penerimaan_stok_id_foreign')
                ->references('id')
                ->on('penerimaan_stok')
                ->onDelete('set null');
            $table->foreign('pengirim_id', 'distribusi_obat_pengirim_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('permintaan_id', 'distribusi_obat_permintaan_id_foreign')
                ->references('id')
                ->on('permintaan_obat')
                ->onDelete('cascade');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->foreign('user_id', 'exports_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('fasilitas_kesehatan', function (Blueprint $table) {
            $table->foreign('puskesmas_induk_id', 'fasilitas_kesehatan_puskesmas_induk_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->foreign('user_id', 'imports_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('inspeksi_retur', function (Blueprint $table) {
            $table->foreign('batch_id', 'inspeksi_retur_batch_id_foreign')
                ->references('id')
                ->on('batch_stok')
                ->onDelete('cascade');
            $table->foreign('detail_retur_id', 'inspeksi_retur_detail_retur_id_foreign')
                ->references('id')
                ->on('detail_retur_obat')
                ->onDelete('cascade');
            $table->foreign('inspected_by', 'inspeksi_retur_inspected_by_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('retur_id', 'inspeksi_retur_retur_id_foreign')
                ->references('id')
                ->on('retur_obat')
                ->onDelete('cascade');
        });

        Schema::table('laporan_lplpo', function (Blueprint $table) {
            $table->foreign('dibuat_oleh', 'laporan_lplpo_dibuat_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('disetujui_oleh', 'laporan_lplpo_disetujui_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('fasilitas_id', 'laporan_lplpo_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('parent_lplpo_id', 'laporan_lplpo_parent_lplpo_id_foreign')
                ->references('id')
                ->on('laporan_lplpo')
                ->onDelete('set null');
            $table->foreign('verifikator_id', 'laporan_lplpo_verifikator_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::table('laporan_rko', function (Blueprint $table) {
            $table->foreign('dibuat_oleh', 'laporan_rko_dibuat_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('disetujui_oleh', 'laporan_rko_disetujui_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('fasilitas_id', 'laporan_rko_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
        });

        Schema::table('model_prediksi', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'model_prediksi_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('obat_id', 'model_prediksi_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('neraca_tahunan', function (Blueprint $table) {
            $table->foreign('dibuat_oleh', 'neraca_tahunan_dibuat_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('fasilitas_id', 'neraca_tahunan_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
        });

        Schema::table('opname_stok', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'opname_stok_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('user_id', 'opname_stok_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('pemakaian_obat', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'pemakaian_obat_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('user_id', 'pemakaian_obat_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('penerimaan_stok', function (Blueprint $table) {
            $table->foreign('distribusi_id', 'penerimaan_stok_distribusi_id_foreign')
                ->references('id')
                ->on('distribusi_obat')
                ->onDelete('set null');
            $table->foreign('fasilitas_id', 'penerimaan_stok_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('sumber_dana_id', 'penerimaan_stok_sumber_dana_id_foreign')
                ->references('id')
                ->on('sumber_dana')
                ->onDelete('set null');
            $table->foreign('supplier_id', 'penerimaan_stok_supplier_id_foreign')
                ->references('id')
                ->on('suppliers')
                ->onDelete('set null');
            $table->foreign('user_id', 'penerimaan_stok_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('pengaturan_laporan', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'pengaturan_laporan_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
        });

        Schema::table('permintaan_obat', function (Blueprint $table) {
            $table->foreign('disetujui_oleh', 'permintaan_obat_disetujui_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('fasilitas_pengirim_id', 'permintaan_obat_fasilitas_pengirim_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('fasilitas_tujuan_id', 'permintaan_obat_fasilitas_tujuan_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('lplpo_id', 'permintaan_obat_lplpo_id_foreign')
                ->references('id')
                ->on('laporan_lplpo')
                ->onDelete('set null');
        });

        Schema::table('prediksi_kebutuhan', function (Blueprint $table) {
            $table->foreign('dibuat_oleh', 'prediksi_kebutuhan_dibuat_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('fasilitas_id', 'prediksi_kebutuhan_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('model_id', 'prediksi_kebutuhan_model_id_foreign')
                ->references('id')
                ->on('model_prediksi')
                ->onDelete('set null');
            $table->foreign('obat_id', 'prediksi_kebutuhan_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('retur_obat', function (Blueprint $table) {
            $table->foreign('disetujui_oleh', 'retur_obat_disetujui_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('distribusi_id', 'retur_obat_distribusi_id_foreign')
                ->references('id')
                ->on('distribusi_obat')
                ->onDelete('set null');
            $table->foreign('fasilitas_penerima_id', 'retur_obat_fasilitas_penerima_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('fasilitas_pengirim_id', 'retur_obat_fasilitas_pengirim_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('penerimaan_id', 'retur_obat_penerimaan_id_foreign')
                ->references('id')
                ->on('penerimaan_stok')
                ->onDelete('set null');
            $table->foreign('supplier_id', 'retur_obat_supplier_id_foreign')
                ->references('id')
                ->on('suppliers')
                ->onDelete('set null');
        });

        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'riwayat_stok_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('obat_id', 'riwayat_stok_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
            $table->foreign('user_id', 'riwayat_stok_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('socialite_users', function (Blueprint $table) {
            $table->foreign('user_id', 'socialite_users_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        Schema::table('stok_faskes', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'stok_faskes_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('obat_id', 'stok_faskes_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('stok_gudang', function (Blueprint $table) {
            $table->foreign('obat_id', 'stok_gudang_obat_id_foreign')
                ->references('id')
                ->on('obat')
                ->onDelete('cascade');
        });

        Schema::table('sumber_dana_penggunaan', function (Blueprint $table) {
            $table->foreign('fasilitas_id', 'sumber_dana_penggunaan_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('cascade');
            $table->foreign('rko_id', 'sumber_dana_penggunaan_rko_id_foreign')
                ->references('id')
                ->on('laporan_rko')
                ->onDelete('set null');
            $table->foreign('sumber_dana_id', 'sumber_dana_penggunaan_sumber_dana_id_foreign')
                ->references('id')
                ->on('sumber_dana')
                ->onDelete('cascade');
        });

        Schema::table('tutup_buku', function (Blueprint $table) {
            $table->foreign('ditutup_oleh', 'tutup_buku_ditutup_oleh_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
            $table->foreign('fasilitas_id', 'tutup_buku_fasilitas_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
            $table->foreign('neraca_id', 'tutup_buku_neraca_id_foreign')
                ->references('id')
                ->on('neraca_tahunan')
                ->onDelete('set null');
        });

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->foreign('user_id', 'user_preferences_user_id_foreign')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('fasilitas_kesehatan_id', 'users_fasilitas_kesehatan_id_foreign')
                ->references('id')
                ->on('fasilitas_kesehatan')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_fasilitas_kesehatan_id_foreign');
        });

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropForeign('user_preferences_user_id_foreign');
        });

        Schema::table('tutup_buku', function (Blueprint $table) {
            $table->dropForeign('tutup_buku_ditutup_oleh_foreign');
            $table->dropForeign('tutup_buku_fasilitas_id_foreign');
            $table->dropForeign('tutup_buku_neraca_id_foreign');
        });

        Schema::table('sumber_dana_penggunaan', function (Blueprint $table) {
            $table->dropForeign('sumber_dana_penggunaan_fasilitas_id_foreign');
            $table->dropForeign('sumber_dana_penggunaan_rko_id_foreign');
            $table->dropForeign('sumber_dana_penggunaan_sumber_dana_id_foreign');
        });

        Schema::table('stok_gudang', function (Blueprint $table) {
            $table->dropForeign('stok_gudang_obat_id_foreign');
        });

        Schema::table('stok_faskes', function (Blueprint $table) {
            $table->dropForeign('stok_faskes_fasilitas_id_foreign');
            $table->dropForeign('stok_faskes_obat_id_foreign');
        });

        Schema::table('socialite_users', function (Blueprint $table) {
            $table->dropForeign('socialite_users_user_id_foreign');
        });

        Schema::table('riwayat_stok', function (Blueprint $table) {
            $table->dropForeign('riwayat_stok_fasilitas_id_foreign');
            $table->dropForeign('riwayat_stok_obat_id_foreign');
            $table->dropForeign('riwayat_stok_user_id_foreign');
        });

        Schema::table('retur_obat', function (Blueprint $table) {
            $table->dropForeign('retur_obat_disetujui_oleh_foreign');
            $table->dropForeign('retur_obat_distribusi_id_foreign');
            $table->dropForeign('retur_obat_fasilitas_penerima_id_foreign');
            $table->dropForeign('retur_obat_fasilitas_pengirim_id_foreign');
            $table->dropForeign('retur_obat_penerimaan_id_foreign');
            $table->dropForeign('retur_obat_supplier_id_foreign');
        });

        Schema::table('prediksi_kebutuhan', function (Blueprint $table) {
            $table->dropForeign('prediksi_kebutuhan_dibuat_oleh_foreign');
            $table->dropForeign('prediksi_kebutuhan_fasilitas_id_foreign');
            $table->dropForeign('prediksi_kebutuhan_model_id_foreign');
            $table->dropForeign('prediksi_kebutuhan_obat_id_foreign');
        });

        Schema::table('permintaan_obat', function (Blueprint $table) {
            $table->dropForeign('permintaan_obat_disetujui_oleh_foreign');
            $table->dropForeign('permintaan_obat_fasilitas_pengirim_id_foreign');
            $table->dropForeign('permintaan_obat_fasilitas_tujuan_id_foreign');
            $table->dropForeign('permintaan_obat_lplpo_id_foreign');
        });

        Schema::table('pengaturan_laporan', function (Blueprint $table) {
            $table->dropForeign('pengaturan_laporan_fasilitas_id_foreign');
        });

        Schema::table('penerimaan_stok', function (Blueprint $table) {
            $table->dropForeign('penerimaan_stok_distribusi_id_foreign');
            $table->dropForeign('penerimaan_stok_fasilitas_id_foreign');
            $table->dropForeign('penerimaan_stok_sumber_dana_id_foreign');
            $table->dropForeign('penerimaan_stok_supplier_id_foreign');
            $table->dropForeign('penerimaan_stok_user_id_foreign');
        });

        Schema::table('pemakaian_obat', function (Blueprint $table) {
            $table->dropForeign('pemakaian_obat_fasilitas_id_foreign');
            $table->dropForeign('pemakaian_obat_user_id_foreign');
        });

        Schema::table('opname_stok', function (Blueprint $table) {
            $table->dropForeign('opname_stok_fasilitas_id_foreign');
            $table->dropForeign('opname_stok_user_id_foreign');
        });

        Schema::table('neraca_tahunan', function (Blueprint $table) {
            $table->dropForeign('neraca_tahunan_dibuat_oleh_foreign');
            $table->dropForeign('neraca_tahunan_fasilitas_id_foreign');
        });

        Schema::table('model_prediksi', function (Blueprint $table) {
            $table->dropForeign('model_prediksi_fasilitas_id_foreign');
            $table->dropForeign('model_prediksi_obat_id_foreign');
        });

        Schema::table('laporan_rko', function (Blueprint $table) {
            $table->dropForeign('laporan_rko_dibuat_oleh_foreign');
            $table->dropForeign('laporan_rko_disetujui_oleh_foreign');
            $table->dropForeign('laporan_rko_fasilitas_id_foreign');
        });

        Schema::table('laporan_lplpo', function (Blueprint $table) {
            $table->dropForeign('laporan_lplpo_dibuat_oleh_foreign');
            $table->dropForeign('laporan_lplpo_disetujui_oleh_foreign');
            $table->dropForeign('laporan_lplpo_fasilitas_id_foreign');
            $table->dropForeign('laporan_lplpo_parent_lplpo_id_foreign');
            $table->dropForeign('laporan_lplpo_verifikator_id_foreign');
        });

        Schema::table('inspeksi_retur', function (Blueprint $table) {
            $table->dropForeign('inspeksi_retur_batch_id_foreign');
            $table->dropForeign('inspeksi_retur_detail_retur_id_foreign');
            $table->dropForeign('inspeksi_retur_inspected_by_foreign');
            $table->dropForeign('inspeksi_retur_retur_id_foreign');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign('imports_user_id_foreign');
        });

        Schema::table('fasilitas_kesehatan', function (Blueprint $table) {
            $table->dropForeign('fasilitas_kesehatan_puskesmas_induk_id_foreign');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign('exports_user_id_foreign');
        });

        Schema::table('distribusi_obat', function (Blueprint $table) {
            $table->dropForeign('distribusi_obat_fasilitas_penerima_id_foreign');
            $table->dropForeign('distribusi_obat_fasilitas_pengirim_id_foreign');
            $table->dropForeign('distribusi_obat_penerima_id_foreign');
            $table->dropForeign('distribusi_obat_penerimaan_stok_id_foreign');
            $table->dropForeign('distribusi_obat_pengirim_id_foreign');
            $table->dropForeign('distribusi_obat_permintaan_id_foreign');
        });

        Schema::table('detail_rko', function (Blueprint $table) {
            $table->dropForeign('detail_rko_lplpo_referensi_id_foreign');
            $table->dropForeign('detail_rko_obat_id_foreign');
            $table->dropForeign('detail_rko_prediksi_id_foreign');
            $table->dropForeign('detail_rko_rko_id_foreign');
        });

        Schema::table('detail_retur_obat', function (Blueprint $table) {
            $table->dropForeign('detail_retur_obat_batch_id_foreign');
            $table->dropForeign('detail_retur_obat_obat_id_foreign');
            $table->dropForeign('detail_retur_obat_retur_id_foreign');
        });

        Schema::table('detail_permintaan_obat', function (Blueprint $table) {
            $table->dropForeign('detail_permintaan_obat_obat_id_foreign');
            $table->dropForeign('detail_permintaan_obat_permintaan_id_foreign');
        });

        Schema::table('detail_penerimaan_stok', function (Blueprint $table) {
            $table->dropForeign('detail_penerimaan_stok_obat_id_foreign');
            $table->dropForeign('detail_penerimaan_stok_penerimaan_id_foreign');
        });

        Schema::table('detail_pemakaian_obat', function (Blueprint $table) {
            $table->dropForeign('detail_pemakaian_obat_batch_id_foreign');
            $table->dropForeign('detail_pemakaian_obat_obat_id_foreign');
            $table->dropForeign('detail_pemakaian_obat_pemakaian_id_foreign');
        });

        Schema::table('detail_opname_stok', function (Blueprint $table) {
            $table->dropForeign('detail_opname_stok_batch_id_foreign');
            $table->dropForeign('detail_opname_stok_obat_id_foreign');
            $table->dropForeign('detail_opname_stok_opname_id_foreign');
        });

        Schema::table('detail_neraca_tahunan', function (Blueprint $table) {
            $table->dropForeign('detail_neraca_tahunan_neraca_id_foreign');
            $table->dropForeign('detail_neraca_tahunan_obat_id_foreign');
        });

        Schema::table('detail_neraca_sumber_dana', function (Blueprint $table) {
            $table->dropForeign('detail_neraca_sumber_dana_detail_neraca_id_foreign');
            $table->dropForeign('detail_neraca_sumber_dana_sumber_dana_id_foreign');
        });

        Schema::table('detail_lplpo', function (Blueprint $table) {
            $table->dropForeign('detail_lplpo_lplpo_id_foreign');
            $table->dropForeign('detail_lplpo_obat_id_foreign');
            $table->dropForeign('detail_lplpo_permintaan_id_foreign');
        });

        Schema::table('detail_distribusi_obat', function (Blueprint $table) {
            $table->dropForeign('detail_distribusi_obat_batch_id_foreign');
            $table->dropForeign('detail_distribusi_obat_distribusi_id_foreign');
            $table->dropForeign('detail_distribusi_obat_obat_id_foreign');
        });

        Schema::table('batch_stok', function (Blueprint $table) {
            $table->dropForeign('batch_stok_fasilitas_id_foreign');
            $table->dropForeign('batch_stok_obat_id_foreign');
            $table->dropForeign('batch_stok_penerimaan_id_foreign');
            $table->dropForeign('batch_stok_sumber_dana_id_foreign');
        });
    }
};
