<?php

namespace Greg\Imagix;

use Greg\Support\Dir;
use Greg\Support\Http\Response;
use Intervention\Image\Constraint;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;

class ImagixTest extends TestCase
{
    private $sourcePath = __DIR__ . '/img';

    private $destinationPath = __DIR__ . '/imagix';

    public function setUp()
    {
        Dir::make($this->destinationPath);
    }

    public function tearDown()
    {
        Dir::unlink($this->destinationPath);
    }

    public function testCanInstantiate()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->assertInstanceOf(Imagix::class, $imagix);
    }

    public function testCanInstantiateWithADecorator()
    {
        $decorator = $this->mockDecorator();

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath, $decorator);

        $this->assertInstanceOf(Imagix::class, $imagix);
    }

    public function testCanGetManager()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->assertInstanceOf(ImageManager::class, $imagix->manager());
    }

    public function testCanGetSourcePath()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->assertEquals($this->sourcePath, $imagix->sourcePath());
    }

    public function testCanGetDestinationPath()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->assertEquals($this->destinationPath, $imagix->destinationPath());
    }

    public function testCanGetDecorator()
    {
        $decorator = $this->mockDecorator();

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath, $decorator);

        $this->assertEquals($decorator, $imagix->decorator());
    }

    public function testCanGetUrl()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $this->assertEquals($this->faviconUrl('foo'), $imagix->url('/favicon.png', 'foo'));
    }

    public function testCanGetDecoratedUrl()
    {
        $decorator = $this->mockDecorator();

        $decorator->method('output')->willReturn('/decorated/path');

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath, $decorator);

        $imagix->format('foo', function () {});

        $this->assertEquals('/decorated/path', $imagix->url('/favicon.png', 'foo'));
    }

    public function testCanGetSource()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->assertEquals(['/favicon.png', 'foo'], $imagix->source($this->faviconUrl('foo')));
    }

    public function testCanGetDecoratedSource()
    {
        $decorator = $this->mockDecorator();

        $decorator->method('input')->willReturn($this->faviconUrl('foo'));

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath, $decorator);

        $this->assertEquals(['/favicon.png', 'foo'], $imagix->source('/foo'));
    }

    public function testCanCompileAnImage()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function (Image $image) {
            $image->resize(128, 128, function (Constraint $constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        });

        $destination = $imagix->url('/favicon.png', 'foo');

        $file = $imagix->compile($destination);

        $this->assertFileExists($file);
    }

    public function testCanCompileAnImageWithADecorator()
    {
        $decorator = $this->mockDecorator();

        $decorator->method('input')->willReturn($this->faviconUrl('foo'));

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath, $decorator);

        $imagix->format('foo', function (Image $image) {
            $image->resize(128, 128, function (Constraint $constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        });

        $file = $imagix->compile('/decorated');

        $this->assertFileExists($file);
    }

    public function testCanNotCompileAnImageIfAlreadyCompiled()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function (Image $image) {
            $image->resize(128, 128, function (Constraint $constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        });

        $file = $imagix->compile($this->faviconUrl('foo'));

        $fileMTime = filemtime($file);

        $newFile = $imagix->compile($this->faviconUrl('foo'));

        $this->assertEquals(filemtime($newFile), $fileMTime);
    }

    public function testCanGetEffectiveUrl()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $this->assertEquals($this->faviconUrl('foo'), $imagix->effective('/favicon@foo@12345.png'));
    }
    
    public function testCanThrowExceptionIfSourceFileNotExists()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->compile('/undefined@foo@12345.png');
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     */
    public function testCanSendAnImage()
    {
        Response::mockHeaders();

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function (Image $image) {
            $image->resize(128, 128, function (Constraint $constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        });

        ob_start();

        $imagix->send($this->faviconUrl('foo'));

        $image = ob_get_clean();

        $this->assertTrue($image === file_get_contents($this->destinationPath . $this->faviconUrl('foo')));
    }

    public function testCanRedirectToAnEffectiveImageUrl()
    {
        Response::mockHeaders();

        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $imagix->send('/favicon@foo@12345.png');

        $this->assertContains('Location: ' . $this->faviconUrl('foo'), Response::mockHeadersSent());
    }

    public function testCanRemoveFilesFromOneFormat()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $destination = $imagix->url('/favicon.png', 'foo');

        $file = $imagix->compile($destination);

        $this->assertFileExists($file);

        $imagix->remove('foo');

        $this->assertFileNotExists($file);
    }

    public function testCanRemoveFilesFromOneFormatUsingLifetime()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $imagix->format('bar', function () {});

        $file1 = $imagix->compile($imagix->url('/favicon.png', 'foo'));

        $this->assertFileExists($file1);

        sleep(1);

        $file2 = $imagix->compile($imagix->url('/favicon.png', 'bar'));

        $this->assertFileExists($file2);

        $imagix->remove('foo', 1);

        $this->assertFileNotExists($file1);

        $this->assertFileExists($file2);
    }

    public function testCanRemoveFilesFromAllFormats()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $imagix->format('bar', function () {});

        $file1 = $imagix->compile($imagix->url('/favicon.png', 'foo'));

        $file2 = $imagix->compile($imagix->url('/favicon.png', 'bar'));

        $this->assertFileExists($file1);

        $this->assertFileExists($file2);

        $imagix->remove();

        $this->assertFileNotExists($file1);

        $this->assertFileNotExists($file2);
    }

    public function testCanRemoveFilesFromAllFormatsUsingLifetime()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $imagix->format('bar', function () {});

        $imagix->format('baz', function () {});

        $file1 = $imagix->compile($imagix->url('/favicon.png', 'foo'));

        $this->assertFileExists($file1);

        $file2 = $imagix->compile($imagix->url('/favicon.png', 'bar'));

        $this->assertFileExists($file2);

        sleep(1);

        $file3 = $imagix->compile($imagix->url('/favicon.png', 'baz'));

        $this->assertFileExists($file3);

        $imagix->remove(null, 1);

        $this->assertFileNotExists($file1);

        $this->assertFileNotExists($file2);

        $this->assertFileExists($file3);
    }

    public function testCanThrowExceptionWhenWrongSource()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->url('', 'foo');
    }

    public function testCanThrowExceptionWhenEmptyDestination()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->source('');
    }

    public function testCanThrowExceptionWhenWrongFormat()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->format('../', function () {});
    }

    public function testCanThrowExceptionWhenFromatNotFound()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->url('/favicon.png', 'undefined');
    }

    public function testCanThrowExceptionWhenFilePathIsNotAllowed()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->compile('/../' . basename(__FILE__));
    }

    public function testCanThrowExceptionWhenWrongDestination()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $this->expectException(\Exception::class);

        $imagix->source('/favicon.png');
    }

    public function testCanReturnSourceUrlIfFileNotFound()
    {
        $imagix = new Imagix(new ImageManager(), $this->sourcePath, $this->destinationPath);

        $imagix->format('foo', function () {});

        $this->assertEquals('/undefined.png', $imagix->url('/undefined.png', 'foo'));
    }

    /**
     * @return ImagixDecoratorStrategy|\PHPUnit_Framework_MockObject_MockObject
     */
    private function mockDecorator(): ImagixDecoratorStrategy
    {
        return $this->getMockBuilder(ImagixDecoratorStrategy::class)->getMock();
    }

    private function faviconUrl(string $format)
    {
        return '/favicon@' . $format . '@' . filemtime(__DIR__ . '/img/favicon.png') . '.png';
    }
}
