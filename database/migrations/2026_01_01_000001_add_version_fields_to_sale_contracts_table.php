<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_contracts', function (Blueprint $table) {
            $table->unsignedTinyInteger('sc_version')->default(1)->after('sc_ve_id_tmp')->comment('合同版本号');
            $table->unsignedTinyInteger('sc_is_current_version')->default(1)->after('sc_version')->comment('是否当前版本');
        });
    }

    public function down(): void
    {
        Schema::table('sale_contracts', function (Blueprint $table) {
            $table->dropColumn(['sc_version', 'sc_is_current_version']);
        });
    }
};
