<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterStockOpnameResultsQuantityColumns extends Migration
{
    public function up()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->float('quantity_good')->nullable()->change();
            $table->float('quantity_reject')->nullable()->change();
            $table->float('quantity_repair')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->integer('quantity_good')->nullable(false)->change();
            $table->integer('quantity_reject')->nullable()->change();
            $table->integer('quantity_repair')->nullable()->change();
        });
    }
}