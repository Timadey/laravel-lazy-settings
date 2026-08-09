<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique('key');
        });

        Schema::create('scoped_test_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['vendor_id', 'key']);
        });

        Schema::create('coercive_test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique('key');
        });

        Schema::create('attribute_test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique('key');
        });

        Schema::create('encrypted_test_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_settings');
        Schema::dropIfExists('scoped_test_settings');
        Schema::dropIfExists('coercive_test_settings');
        Schema::dropIfExists('attribute_test_settings');
        Schema::dropIfExists('encrypted_test_settings');
    }
};