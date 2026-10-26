<?php

/**
 * Created by PhpStorm.
 * User: hugh.li
 * Date: 2022/2/18
 * Time: 23:17.
 */

namespace HughCube\Laravel\Knight\Tests\Support;

use Exception;
use GuzzleHttp\RequestOptions;
use HughCube\GuzzleHttp\Client;
use HughCube\Laravel\Knight\Support\Downloader;
use HughCube\Laravel\Knight\Tests\TestCase;
use HughCube\StaticInstanceInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DownloaderTest extends TestCase
{
    public function testPath()
    {
        $url = 'https://www.example.com/';

        $this->assertSame(File::extension($url), File::extension(Downloader::path($url)));
        $this->assertTrue(Str::startsWith(Downloader::path($url), storage_path()));
    }

    public function testStaticInstance()
    {
        $this->assertInstanceOf(StaticInstanceInterface::class, Downloader::instance());
        $this->assertSame(Downloader::instance(), Downloader::instance());
        $this->assertNotSame(Downloader::instance(), Downloader::instance(true));
    }

    public function testTo()
    {
        $downloader = $this->makeDownloaderWithStatus(200);
        $file = $this->tempFilePath('to.txt');

        $this->assertSame($file, $downloader->to('get', 'https://www.example.com/', $file, [
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
        ]));
        $this->assertFileExists($file);
        $this->cleanupFile($file);
    }

    public function testGet()
    {
        $this->makeDownloaderWithStatus(200);

        $file = TestDownloader::get('https://www.example.com/', null, [
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertFileExists($file);
        $this->cleanupFile($file);
    }

    public function testSave()
    {
        $this->makeDownloaderWithStatus(200);

        $file = TestDownloader::save('https://www.example.com/', null, [
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertFileExists($file);
        $this->cleanupFile($file);
    }

    public function testToThrowsOnNonSuccessStatus()
    {
        $downloader = $this->makeDownloaderWithStatus(500);
        $file = $this->tempFilePath('error.txt');

        try {
            $downloader->to('get', 'https://www.example.com/', $file, [
                RequestOptions::VERIFY => false,
                RequestOptions::HTTP_ERRORS => false,
            ]);
            $this->fail('Expected Exception was not thrown.');
        } catch (Exception $exception) {
            $this->assertSame('File download failure.', $exception->getMessage());
        } finally {
            $this->cleanupFile($file);
        }
    }

    private function makeDownloaderWithStatus(int $statusCode): TestDownloader
    {
        TestDownloader::$client = new StubClient($statusCode);

        return TestDownloader::instance(true);
    }

    private function tempFilePath(string $name): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-downloader-'.Carbon::now()->format('YmdHis').'-'.random_int(1000, 9999);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir.DIRECTORY_SEPARATOR.$name;
    }

    private function cleanupFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }

        $dir = dirname($path);
        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }
}

class TestDownloader extends Downloader
{
    public static ?Client $client = null;

    protected function createHttpClient(): Client
    {
        return static::$client ?? new StubClient(200);
    }
}

class StubClient extends Client
{
    private int $statusCode;
    private string $body;

    public function __construct(int $statusCode, string $body = 'stub')
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    public function request($method, $uri = '', array $options = [])
    {
        if (isset($options[RequestOptions::SINK]) && is_string($options[RequestOptions::SINK])) {
            $file = $options[RequestOptions::SINK];
            $dir = dirname($file);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($file, $this->body);
        }

        return new class($this->statusCode)
        {
            private int $statusCode;

            public function __construct(int $statusCode)
            {
                $this->statusCode = $statusCode;
            }

            public function getStatusCode(): int
            {
                return $this->statusCode;
            }
        };
    }
}
