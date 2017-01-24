<?php

namespace Greg\StaticImage\Tests;

use Greg\StaticImage\StaticImageManager;
use Greg\Support\Dir;
use Intervention\Image\Constraint;
use Intervention\Image\Image;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\TestCase;

class ImageCollectorTest extends TestCase
{
    /**
     * @var StaticImageManager
     */
    private $collector = null;

    public function setUp()
    {
        parent::setUp();

        Dir::make(__DIR__ . '/static');

        $this->collector = new StaticImageManager(new ImageManager(), __DIR__ . '/img', __DIR__ . '/static');

        $this->collector->format('favicon', function (Image $image) {
            $image->resize(128, 128, function (Constraint $constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        });
    }

    public function tearDown()
    {
        parent::tearDown();

        Dir::unlink(__DIR__ . '/static');
    }

    /** @test */
    public function it_checks_data()
    {
        $this->assertEquals(__DIR__ . '/img', $this->collector->sourcePath());

        $this->assertEquals(__DIR__ . '/static', $this->collector->destinationPath());

        $this->assertInstanceOf(ImageManager::class, $this->collector->manager());
    }

    /** @test */
    public function it_gets_formatted_url()
    {
        $destination = '/favicon@favicon@' . filemtime(__DIR__ . '/img/favicon.png') . '.png';

        $this->assertEquals($destination, $this->collector->url('/favicon.png', 'favicon'));

        $this->assertEquals($destination, $this->collector->url('/favicon.png', 'favicon'));

        $this->assertEquals(['/favicon.png', 'favicon'], $this->collector->source($destination));
    }

    /** @test */
    public function it_returns_empty()
    {
        $this->assertEquals('/foo', $this->collector->url('/foo', 'favicon'));
    }

    /** @test */
    public function it_throws_an_exception_when_format_not_found()
    {
        $this->expectException(\Exception::class);

        $this->collector->url('/favicon.png', 'undefined');
    }

    /** @test */
    public function it_throws_an_error_when_get_wrong_source()
    {
        $this->expectException(\Exception::class);

        $this->collector->source('');
    }

    /** @test */
    public function it_throws_an_error_when_get_wrong_destination()
    {
        $this->expectException(\Exception::class);

        $this->collector->url('', 'format');
    }

    /** @test */
    public function it_formats_an_image()
    {
        $destination = '/favicon@favicon@' . filemtime(__DIR__ . '/img/favicon.png') . '.png';

        $this->assertEquals(__DIR__ . '/static' . $destination, $this->collector->image($destination));

        // Load from cache
        $this->assertEquals(__DIR__ . '/static' . $destination, $this->collector->image($destination));
    }

    /** @test */
    public function it_throws_an_error_when_get_wrong_file()
    {
        $this->expectException(\Exception::class);

        $this->collector->image('');
    }

    /** @test */
    public function it_throws_an_error_when_get_wrong_destination_on_file()
    {
        $this->expectException(\Exception::class);

        $this->collector->image('/foo');
    }

    /** @test */
    public function it_throws_an_error_when_source_not_exists_on_file()
    {
        $this->expectException(\Exception::class);

        copy(__DIR__ . '/img/favicon.png', __DIR__ . '/img/favicon2.png');

        $destination = '/favicon2@favicon@' . filemtime(__DIR__ . '/img/favicon2.png') . '.png';

        $this->collector->image($destination);

        unlink(__DIR__ . '/img/favicon2.png');

        $this->collector->image($destination);
    }

    /** @test */
    public function it_throws_an_error_when_source_not_exists_on_file_2()
    {
        $this->expectException(\Exception::class);

        $this->collector->image('/../ImageCollectorTest.php');
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     */
    public function it_sends_an_image()
    {
        $destination = '/favicon@favicon@' . filemtime(__DIR__ . '/img/favicon.png') . '.png';

        ob_start();

        $this->collector->send($destination);

        $image = ob_get_clean();

        $this->assertTrue($image === file_get_contents(__DIR__ . '/static' . $destination));
    }

    /**
     * @test
     *
     * @runInSeparateProcess
     */
    public function it_redirects_when_old_image()
    {
        $this->collector->send('/favicon@favicon@12345.png');
    }
}
