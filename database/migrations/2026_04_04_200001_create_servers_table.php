<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('host');
            $table->integer('port')->default(22);
            $table->string('ssh_user')->default('root');
            $table->text('ssh_private_key');
            $table->string('ssh_fingerprint')->nullable();
            $table->boolean('script_installed')->default(false);
            $table->timestamp('last_connected_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
