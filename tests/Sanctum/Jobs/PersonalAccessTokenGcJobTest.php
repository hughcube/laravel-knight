<?php

namespace HughCube\Laravel\Knight\Tests\Sanctum\Jobs;

use HughCube\Laravel\Knight\Sanctum\Jobs\PersonalAccessTokenGcJob;
use HughCube\Laravel\Knight\Sanctum\PersonalAccessToken;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PersonalAccessTokenGcJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tokenable_type');
            $table->unsignedBigInteger('tokenable_id');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function testDeletesExpiredTokens()
    {
        $expired = Carbon::now()->subDays(400);
        $fresh = Carbon::now()->subDays(10);

        PersonalAccessToken::query()->insert([
            [
                'tokenable_type' => 'User',
                'tokenable_id' => 1,
                'name' => 'expired',
                'token' => hash('sha256', 'expired'),
                'abilities' => json_encode([]),
                'last_used_at' => $expired,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'tokenable_type' => 'User',
                'tokenable_id' => 2,
                'name' => 'fresh',
                'token' => hash('sha256', 'fresh'),
                'abilities' => json_encode([]),
                'last_used_at' => $fresh,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $job = new PersonalAccessTokenGcJob();
        $job->handle();

        $this->assertSame(1, PersonalAccessToken::query()->count());
        $this->assertSame('fresh', PersonalAccessToken::query()->first()->name);
    }
}
