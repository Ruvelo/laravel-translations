<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('translations.table_prefix', 'translations_');

        Schema::create($prefix.'overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 20);
            // '*' for your app's own files; a package name for lang/vendor.
            $table->string('namespace', 100)->default('*');
            // The file the key lives in, or '*' for the JSON file.
            $table->string('group', 150);
            $table->text('key');
            // sha1 of namespace, group and key: keys can be long sentences,
            // too long for a unique index on every database.
            $table->char('hash', 40);
            $table->text('value');
            // What the lang file said when the edit was made, to tell when the
            // file has changed underneath it.
            $table->text('file_value')->nullable();
            // A string so UUID and ULID user keys work as well as integers.
            $table->string('updated_by', 64)->nullable();
            $table->timestamps();

            $table->unique(['locale', 'hash']);
            $table->index(['locale', 'namespace', 'group']);
        });

        Schema::create($prefix.'locales', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = config('translations.table_prefix', 'translations_');

        Schema::dropIfExists($prefix.'locales');
        Schema::dropIfExists($prefix.'overrides');
    }
};
