<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('sc_group_no')->nullable()->after('sc_id')->comment('续租合同分组');
            $table->unsignedInteger('sc_group_seq')->default(1)->after('sc_group_no')->comment('分组内顺序');
        });
    }

    public function down(): void
    {
        Schema::table('sale_contracts', function (Blueprint $table) {
            $table->dropIndex(['sc_group_no']);
            $table->dropColumn(['sc_group_no', 'sc_group_seq']);
        });
    }
};
