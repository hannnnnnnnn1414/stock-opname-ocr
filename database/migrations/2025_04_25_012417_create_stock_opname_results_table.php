<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockOpnameResultsTable extends Migration
{
    public function up()
    {
        Schema::create('stock_opname_results', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id');
            $table->date('tanggal');
            $table->time('jam');
            $table->string('location');
            $table->string('warehouse');
            $table->string('nomor_form');
            $table->string('nama_part');
            $table->string('nomor_part');
            $table->string('satuan');
            $table->string('quantity_good_raw')->nullable();
            $table->string('quantity_good')->nullable();
            $table->string('quantity_reject_raw')->nullable();
            $table->string('quantity_reject')->nullable();
            $table->string('quantity_repair_raw')->nullable();
            $table->string('quantity_repair')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('stock_opname_results');
    }
}