<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection($this->connection())->create($this->table(), function (Blueprint $table): void {
            $table->id();

            // The fixed core, one column per key. NULL means the brand does
            // not define the key and inherits it from the default brand; an
            // empty string means the brand cleared it.
            $table->string('identifier')->unique();
            $table->string('name')->nullable();
            $table->string('domain')->nullable()->unique();

            $table->string('logo_disk')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('favicon_disk')->nullable();
            $table->string('favicon_path')->nullable();

            $table->json('colors')->nullable();

            $table->string('mail_from_name')->nullable();
            $table->string('mail_from_address')->nullable();

            // The open bag, for everything the fixed core does not name.
            $table->json('settings')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists($this->table());
    }

    private function table(): string
    {
        $table = config('whitelabel.database.table');

        return is_string($table) && $table !== '' ? $table : 'brands';
    }

    private function connection(): ?string
    {
        $connection = config('whitelabel.database.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
};
