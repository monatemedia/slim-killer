<?php
use Illuminate\Database\Schema\Blueprint;
use illuminate\Database\Capsule\Manager as Capsule;

return new class {
    public function up() {
        Capsule::schema()->create('subscribers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    public function down() {
        Capsule::schema()->dropIfExists('subscribers');
    }
};