<?php
namespace Bono\Test\Http;

use PHPUnit\Framework\TestCase;
use Bono\Http\Headers;
use Bono\Exception\BonoException;

class HeadersTest extends TestCase
{
    public function testConstruct()
    {
        try {
            $headers = new Headers('none');
            $this->fail('Must not here');
        } catch (BonoException $e) {
            if ($e->getMessage() !== 'Init headers must be traversable') {
                throw $e;
            }
            $this->assertTrue(true);
        }
    }

    public function testNormalize()
    {
        $headers = new Headers();
        $headers->add('x-foo', 'bar');
        $headers->add('x-foo', 'baz');
        $normalized = $headers->normalize();
        $this->assertEquals($normalized['X-Foo'][0], 'bar');
        $this->assertEquals($normalized['X-Foo'][1], 'baz');
    }

    public function testAdd()
    {
        $headers = new Headers();
        $headers->add('x-foo', ['bar', 'baz']);
        $this->assertEquals($headers['x-foo'], ['bar', 'baz']);
    }

    public function testByEnvironment()
    {
        $env = [
            'HTTP_X_FOO' => 'bar'
        ];
        $headers = Headers::byEnvironment($env);
        $this->assertEquals($headers['x-foo'], ['bar']);
    }
}
