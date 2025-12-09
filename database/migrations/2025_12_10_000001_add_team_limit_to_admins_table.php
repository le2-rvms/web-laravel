<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('admins', 'team_limit')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->unsignedTinyInteger('team_limit')->default(0)->comment('0:不限定;1:限定');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('admins', 'team_limit')) {
            return;
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('team_limit');
        });
    }
};
