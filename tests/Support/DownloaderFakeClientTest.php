<?php

namespace HughCube\Laravel\Knight\Tests\Support;

use GuzzleHttp\RequestOptions;
use HughCube\GuzzleHttp\Client;
use HughCube\Laravel\Knight\Support\Downloader;
use HughCube\Laravel\Knight\Tests\TestCase;

class DownloaderFakeClientTest extends TestCase
{
    public function testToAndDynamicMethodsWithFakeClient()
    {
        $downloader = new class() extends Downloader
        {
            protected function createHttpClient(): Client
            {
                return new FakeClient();
            }
        };

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'knight-download-'.uniqid();
        $file = $dir.DIRECTORY_SEPARATOR.'file.txt';

        $result = $downloader->to('get', 'http://example.test', $file);
        $this->assertSame($file, $result);
        $this->assertFileExists($file);

        $file2 = $dir.DIRECTORY_SEPARATOR.'file2.txt';
        $result = $downloader->get('http://example.test', $file2);
        $this->assertSame($file2, $result);
        $this->assertFileExists($file2);

        $file3 = $dir.DIRECTORY_SEPARATOR.'file3.txt';
        $result = $downloader->save('http://example.test', $file3);
        $this->assertSame($file3, $result);
        $this->assertFileExists($file3);

        $this->cleanupFile($file);
        $this->cleanupFile($file2);
        $this->cleanupFile($file3);

        if (is_dir($dir)) {
            @rmdir($dir);
        }
    }

    private function cleanupFile(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

class FakeClient extends Client
{
    public function __construct()
    {
    }

    public function request($method, $uri = '', array $options = [])
    {
        if (isset($options[RequestOptions::SINK]) && is_string($options[RequestOptions::SINK])) {
            $file = $options[RequestOptions::SINK];
            $dir = dirname($file);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($file, 'stub');
        }

        return new class()
        {
            public function getStatusCode(): int
            {
                return 200;
            }

            public function getBody()
            {
                return new class()
                {
                    public function getContents(): string
                    {
                        return '';
                    }
                };
            }
        };
    }
}
