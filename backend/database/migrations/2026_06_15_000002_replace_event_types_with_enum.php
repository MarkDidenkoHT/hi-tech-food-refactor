<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_type_id');
            $table->string('event_type')->nullable()->after('restaurant_id');
        });

        Schema::dropIfExists('event_types');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('event_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->default('#3498db');
            $table->timestamps();

            $table->unique(['restaurant_id', 'slug']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_type');
            $table->foreignId('event_type_id')->nullable()->after('restaurant_id')->constrained()->nullOnDelete();
        });
    }
};
