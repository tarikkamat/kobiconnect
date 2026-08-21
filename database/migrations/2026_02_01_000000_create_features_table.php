<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Migrations\PennantMigration;

/**
 * Pennant deposu. `PennantMigration::getConnection()` baglantiyi
 * `pennant.stores.database.connection` (= central) uzerinden alir, boylece
 * tablo tenant semasina degil central semaya kurulur.
 */
return new class extends PennantMigration
{
    public function up(): void
    {
        Schema::connection($this->getConnection())->create('features', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('scope');
            $table->text('value');
            $table->timestamps();

            $table->unique(['name', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('features');
    }
};
