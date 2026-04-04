<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ip_server', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('blocked_ip_id')->constrained('blocked_ips')->cascadeOnDelete();
            $table->foreignUlid('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('unblocked_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ip_server');
    }
};
