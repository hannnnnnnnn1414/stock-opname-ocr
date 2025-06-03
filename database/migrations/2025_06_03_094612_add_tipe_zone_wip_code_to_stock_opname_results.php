<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTipeZoneWipCodeToStockOpnameResults extends Migration
{
    public function up()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->string('tipe')->nullable()->after('satuan');
            $table->string('zone')->nullable()->after('tipe');
            $table->string('wip_code')->nullable()->after('zone');
        });
    }

    public function down()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->dropColumn(['tipe', 'zone', 'wip_code']);
        });
    }
}