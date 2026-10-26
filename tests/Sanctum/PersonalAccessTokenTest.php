<?php

namespace HughCube\Laravel\Knight\Tests\Sanctum;

use HughCube\Laravel\Knight\Sanctum\PersonalAccessToken;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PersonalAccessTokenTest extends TestCase
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
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function testOnChangeRefreshCacheKeysIncludesToken(): void
    {
        $token = new PersonalAccessToken();
        $token->id = 10;
        $token->token = 'hash';

        $this->assertSame(
            [
                ['id' => 10],
                ['token' => 'hash'],
            ],
            $token->onChangeRefreshCacheKeys()
        );
    }

    public function testAccessSecretAndSource(): void
    {
        $token = new PersonalAccessToken();
        $token->abilities = [
            'access_secret' => 'secret',
            'source' => 'api',
        ];

        $this->assertSame('secret', $token->getAccessSecret());
        $this->assertSame('api', $token->getSource());

        $token->abilities = [
            'access_secret' => '',
            'source' => '',
        ];

        $this->assertNull($token->getAccessSecret());
        $this->assertNull($token->getSource());
    }

    public function testIsValidAccessTokenReturnsTrueForNullLastUsedAt(): void
    {
        $token = new PersonalAccessToken();
        $token->last_used_at = null;

        $this->assertTrue($token->isValidAccessToken());
    }

    public function testIsValidAccessTokenReturnsTrueWhenLastUsedAtExists(): void
    {
        $token = new PersonalAccessToken();
        $token->last_used_at = Carbon::now()->subDays(400);

        $this->assertTrue($token->isValidAccessToken());
    }

    public function testSaveSkipsWhenRecentLastUsedAt(): void
    {
        $token = new PersonalAccessToken();
        $token->last_used_at = Carbon::now()->subHour();

        $this->assertTrue($token->skipSave());
        $this->assertTrue($token->save());
    }

    public function testSkipSaveReturnsFalseWhenOtherFieldsDirty(): void
    {
        $token = new PersonalAccessToken();
        $token->last_used_at = Carbon::now()->subHour();
        $token->name = 'token';

        $this->assertFalse($token->skipSave());
    }

    public function testFindTokenWithPlainToken(): void
    {
        $plain = 'plain-token';

        PersonalAccessToken::query()->insert([
            'id' => 10,
            'tokenable_type' => TokenableStub::class,
            'tokenable_id' => 5,
            'name' => 'example',
            'token' => hash('sha256', $plain),
            'abilities' => json_encode(['access_secret' => 'secret', 'source' => 'web']),
            'last_used_at' => Carbon::now()->subDay(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $token = PersonalAccessToken::findToken($plain);

        $this->assertInstanceOf(PersonalAccessToken::class, $token);
        $this->assertSame(10, $token->id);
        $this->assertSame('example', $token->name);
    }

    public function testFindTokenWithIdAndToken(): void
    {
        $plain = 'split-token';

        PersonalAccessToken::query()->insert([
            'id' => 11,
            'tokenable_type' => TokenableStub::class,
            'tokenable_id' => 9,
            'name' => 'split',
            'token' => hash('sha256', $plain),
            'abilities' => json_encode([]),
            'last_used_at' => Carbon::now()->subDay(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $token = PersonalAccessToken::findToken('11|'.$plain);

        $this->assertInstanceOf(PersonalAccessToken::class, $token);
        $this->assertSame(11, $token->id);
        $this->assertSame('split', $token->name);

        $this->assertNull(PersonalAccessToken::findToken('11|wrong-token'));
    }

    public function testTokenableAttributeResolvesType(): void
    {
        TokenableStub::$lastId = null;

        $token = new PersonalAccessToken();
        $token->setRawAttributes([
            'tokenable_type' => TokenableStub::class,
            'tokenable_id' => 42,
        ]);

        $resolved = $token->tokenable;

        $this->assertSame(42, $resolved->id);
        $this->assertSame(42, TokenableStub::$lastId);
    }
}

class TokenableStub
{
    public static ?int $lastId = null;

    public static function findById($id)
    {
        self::$lastId = $id;

        return (object) ['id' => $id];
    }
}
