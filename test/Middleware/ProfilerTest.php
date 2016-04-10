<?php

namespace Bono\Test\Middleware;

use Bono\Middleware\Profiler;
use Bono\Test\BonoTestCase;
use Bono\Http\Context;
use Bono\Http\Request;
use Bono\Http\Response;

class ProfilerTest extends BonoTestCase
{
    public function testInvoke()
    {
        $m = new Profiler();

        $context = $this->getMock(Context::class, [], [
            $this->app,
            $this->getMock(Request::class),
            $this->getMock(Response::class),
        ]);

        $set = [];

        $context->expects($this->any())
            ->method('setHeader')
            ->will($this->returnCallback(function ($key) use ($context, &$set) {
                $set[] = $key;
                return $context;
            }));
        $next = function () {
        };

        $m($context, $next);

        $this->assertContains('X-Profiler-Response-Time', $set);
        $this->assertContains('X-Profiler-Memory-Usage', $set);
        $this->assertContains('X-Profiler-Peak-Memory-Usage', $set);
    }
}