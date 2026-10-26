<?php

namespace HughCube\Laravel\Knight\Tests\Queue\Jobs;

use HughCube\Laravel\Knight\Queue\Jobs\CacheTableGcJob;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CacheTableGcJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('cache_items');
        Schema::dropIfExists('cache_locks');

        Schema::create('cache_items', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->integer('expiration');
        });
    }

    public function testDeletesExpiredRows()
    {
        $now = Carbon::now()->getTimestamp();

        DB::table('cache_items')->insert([
            ['key' => 'old', 'value' => 'x', 'expiration' => $now - 2000],
            ['key' => 'new', 'value' => 'y', 'expiration' => $now + 2000],
        ]);

        DB::table('cache_locks')->insert([
            ['key' => 'old', 'value' => 'x', 'expiration' => $now - 2000],
            ['key' => 'new', 'value' => 'y', 'expiration' => $now + 2000],
        ]);

        $job = new CacheTableGcJob([
            'cache_table' => 'cache_items',
            'lock_table' => 'cache_locks',
        ]);
        $job->handle();

        $this->assertSame(1, DB::table('cache_items')->count());
        $this->assertSame(1, DB::table('cache_locks')->count());
    }
}
