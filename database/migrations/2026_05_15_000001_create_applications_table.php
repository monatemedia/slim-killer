<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Capsule\Manager as Capsule;

return new class {
    public function up() {
        Capsule::schema()->create('applications', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->decimal('bond_amount', 15, 2);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    public function down() {
        Capsule::schema()->dropIfExists('applications');
    }
};