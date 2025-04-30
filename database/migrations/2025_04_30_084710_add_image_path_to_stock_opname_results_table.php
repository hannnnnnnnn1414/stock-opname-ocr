<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->string('image_path')->nullable(); // Add this line
        });
    }

    public function down()
    {
        Schema::table('stock_opname_results', function (Blueprint $table) {
            $table->dropColumn('image_path'); // Add this line
        });
    }
};
