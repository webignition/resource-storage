<?php
/** @noinspection PhpDocSignatureInspection */

namespace webignition\ResourceStorage\Tests;

use phpmock\mockery\PHPMockery;
use webignition\ResourceStorage\ResourceStorage;
use webignition\ResourceStorage\SourcePurger;
use webignition\UrlSourceMap\Source;
use webignition\UrlSourceMap\SourceMap;

class ResourceStorageTest extends \PHPUnit\Framework\TestCase
{
    const MICROTIME = 123;

    /**
     * @dataProvider storeDataProvider
     */
    public function testStore(
        string $uri,
        string $content,
        string $type,
        string $filenameHash,
        string $expectedMappedUri
    ) {
        $this->mockMicrotime(self::MICROTIME);
        $this->mockMd5($content . self::MICROTIME, $filenameHash);

        $localSources = new SourceMap();
        $resourceStorage = new ResourceStorage();

        $source = $resourceStorage->store($localSources, $uri, $content, $type);
        $expectedSource = new Source($uri, $expectedMappedUri);
        $expectedPath = str_replace('file:', '', $expectedMappedUri);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals($expectedSource, $source);
        $this->assertEquals($expectedSource, $localSources[$uri]);
        $this->assertStoredFile($expectedPath, $content);

        $sourcePurger = new SourcePurger();
        $sourcePurger->purgeLocalResources($localSources);

        $this->assertFalse(file_exists($expectedPath));
    }

    public function storeDataProvider(): array
    {
        return [
            'html file' => [
                'uri' => 'http://example.com/index.html',
                'content' => '<!doctype html><html></html>',
                'type' => 'html',
                'filenameHash' => 'file-hash-1',
                'expectedMappedUri' => 'file:/tmp/file-hash-1.html',
            ],
            'css file' => [
                'uri' => 'http://example.com/style.css',
                'content' => 'html {}',
                'type' => 'css',
                'filenameHash' => 'file-hash-2',
                'expectedMappedUri' => 'file:/tmp/file-hash-2.css',
            ],
        ];
    }

    public function testDuplicate()
    {
        $uri = 'http://example.com/index.html';
        $content = '<!doctype html><html></html>';
        $type = 'html';
        $filenameHash = 'file-hash';
        $expectedMappedUri = 'file:/tmp/file-hash.html';

        $localPath = sys_get_temp_dir() . '/' . md5((string) microtime(true));

        file_put_contents($localPath, $content);

        $this->assertTrue(file_exists($localPath));

        $this->mockMicrotime(self::MICROTIME);
        $this->mockMd5($localPath . self::MICROTIME, $filenameHash);

        $localSources = new SourceMap();
        $resourceStorage = new ResourceStorage();

        $source = $resourceStorage->duplicate($localSources, $uri, $localPath, $type);
        $expectedSource = new Source($uri, $expectedMappedUri);
        $expectedPath = str_replace('file:', '', $expectedMappedUri);

        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals($expectedSource, $source);
        $this->assertEquals($expectedSource, $localSources[$uri]);
        $this->assertStoredFile($expectedPath, $content);

        $sourcePurger = new SourcePurger();
        $sourcePurger->purgeLocalResources($localSources);

        $this->assertFalse(file_exists($expectedPath));
        @unlink($localPath);
    }

    private function assertStoredFile(string $path, string $expectedContent)
    {
        $this->assertTrue(file_exists($path));
        $this->assertEquals($expectedContent, file_get_contents($path));
    }

    private function mockMicrotime(int $time)
    {
        PHPMockery::mock(
            'webignition\ResourceStorage',
            'microtime'
        )->andReturn($time);
    }

    private function mockMd5(string $expectedInput, string $output)
    {
        PHPMockery::mock(
            'webignition\ResourceStorage',
            'md5'
        )->withArgs(function (string $input) use ($expectedInput) {
            $this->assertSame($input, $expectedInput);

            return true;
        })->andReturn($output);
    }

    protected function tearDown()
    {
        parent::tearDown();

        \Mockery::close();
    }
}
