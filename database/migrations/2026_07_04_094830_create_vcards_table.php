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
        Schema::create('vcards', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->string('slug')->unique();

            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('job_title')->nullable();

            $table->string('email')->nullable();
            $table->string('phone_work')->nullable();
            $table->string('phone_mobile')->nullable();
            $table->text('phones')->nullable();
            $table->text('emails')->nullable();
            $table->text('sites')->nullable();
            $table->string('website')->nullable();

            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->string('location_input_type')->nullable();
            $table->string('location_label', 80)->nullable();
            $table->string('location_icon', 50)->nullable();
            $table->string('location_search')->nullable();
            $table->string('location_url')->nullable();
            $table->string('latitude', 60)->nullable();
            $table->string('longitude', 60)->nullable();
            $table->text('companies')->nullable();

            $table->text('note')->nullable();

            $table->string('facebook')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('twitter')->nullable();
            $table->string('instagram')->nullable();
            $table->text('social_links')->nullable();
            $table->boolean('show_social_name')->default(false);
            $table->boolean('show_social_as_cards')->default(false);
            $table->text('social_icon_mode')->nullable();
            $table->text('social_custom_icons')->nullable();

            $table->string('theme')->default('modern');
            $table->string('font_family')->default('Poppins');

            $table->string('accent_color')->default('#06b6d4');
            $table->string('bg_color')->default('#0f172a');
            $table->string('text_color')->default('#ffffff');
            $table->string('card_bg')->default('#1e293b');

            $table->string('card_style')->default('standard');

            $table->string('banner_path')->nullable();
            $table->string('profile_path')->nullable();

            $table->string('loading_path')->nullable();
            $table->boolean('loading_screen_enabled')->default(false);
            $table->unsignedTinyInteger('loading_time')->default(2);

            $table->string('qr_logo_path')->nullable();
            $table->boolean('qr_has_logo')->default(true);
            $table->string('qr_logo_mode', 20)->default('none');
            $table->text('preview_section_order')->nullable();
            $table->string('contact_button_text', 80)->default('Save Contact');
            $table->string('contact_button_position', 20)->default('top');
            $table->string('button_text_color', 20)->nullable();
            $table->boolean('avatar_ring_enabled')->default(true);
            $table->string('avatar_ring_color', 20)->nullable();
            $table->unsignedTinyInteger('avatar_ring_width')->default(4);
            $table->boolean('floating_button_ring_enabled')->default(true);
            $table->string('floating_button_ring_color', 20)->nullable();
            $table->unsignedTinyInteger('floating_button_ring_width')->default(4);
            $table->string('floating_button_ring_shape', 20)->default('circle');
            $table->string('floating_button_placement', 20)->default('bottom-right');
            $table->unsignedTinyInteger('floating_button_border_radius')->default(56);
            $table->unsignedTinyInteger('avatar_border_radius')->default(56);
            $table->string('field_border_color', 20)->nullable();
            $table->unsignedTinyInteger('field_border_radius')->default(12);
            $table->unsignedTinyInteger('field_border_width')->default(1);
            $table->string('field_border_style', 20)->default('solid');
            $table->string('field_shadow', 20)->default('soft');

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vcards');
    }
};
