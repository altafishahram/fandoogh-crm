<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_provinces', function (Blueprint $table): void {
            $table->id();
            $table->string('source_code', 20)->unique();
            $table->string('name', 100);
        });
        Schema::create('location_counties', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('province_id')->constrained('location_provinces')->restrictOnDelete();
            $table->string('source_code', 20)->unique();
            $table->string('name', 100);
        });
        Schema::create('location_cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('county_id')->constrained('location_counties')->restrictOnDelete();
            $table->string('source_code', 20)->unique();
            $table->string('name', 100);
        });
        foreach (['agencies', 'properties', 'customers', 'owners'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $prefix = $name === 'customers' ? 'desired_' : '';
                $table->foreignId($prefix.'province_id')->nullable()->constrained('location_provinces')->restrictOnDelete();
                $table->foreignId($prefix.'county_id')->nullable()->constrained('location_counties')->restrictOnDelete();
                $table->foreignId($prefix.'city_id')->nullable()->constrained('location_cities')->restrictOnDelete();
            });
        }
        Schema::table('agencies', function (Blueprint $table): void {
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::create('public_users', function (Blueprint $table): void {
            $table->id();
            $table->string('phone', 16)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('property_publications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_id')->constrained('agencies')->restrictOnDelete();
            $table->unsignedBigInteger('property_id')->unique();
            $table->foreign(['agency_id', 'property_id'])->references(['agency_id', 'id'])->on('properties')->restrictOnDelete();
            $table->boolean('share_with_agencies')->default(false);
            $table->boolean('publish_public')->default(false);
            $table->string('public_title', 200);
            $table->text('public_description')->nullable();
            $table->unsignedBigInteger('responding_user_id');
            $table->foreign(['agency_id', 'responding_user_id'])->references(['agency_id', 'id'])->on('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['publish_public', 'published_at']);
            $table->index(['share_with_agencies', 'published_at']);
        });
        Schema::create('publication_images', function (Blueprint $table): void {
            $table->foreignId('publication_id')->constrained('property_publications')->cascadeOnDelete();
            $table->foreignId('property_image_id')->constrained('property_images')->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->primary(['publication_id', 'property_image_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_images');
        Schema::dropIfExists('property_publications');
        Schema::dropIfExists('public_users');
        Schema::table('agencies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn(['is_verified', 'verified_at']);
        });
        foreach (['agencies', 'properties', 'customers', 'owners'] as $name) {
            Schema::table($name, function (Blueprint $table) use ($name): void {
                $prefix = $name === 'customers' ? 'desired_' : '';
                foreach (['province_id', 'county_id', 'city_id'] as $field) {
                    $table->dropConstrainedForeignId($prefix.$field);
                }
            });
        }
        Schema::dropIfExists('location_cities');
        Schema::dropIfExists('location_counties');
        Schema::dropIfExists('location_provinces');
    }
};
