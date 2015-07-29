<?php

use \Gsaulmon\GuzzleRecorder\GuzzleRecorder;
use GuzzleHttp\Psr7\Request;

class GuzzleRecorderTest extends PHPUnit_Framework_TestCase
{
    /** @var GuzzleHttp\Client $client */
    private $client = null;
    private $recorder = null;

    public function setup()
    {
        $this->client = new GuzzleHttp\Client([
            'defaults' => [
                'headers' => [
                    'User-Agent' => 'GuzzleRecorder'
                ]
            ]
        ]);

        $this->recorder = new GuzzleRecorder(__DIR__ . '/responses');
        $this->recorder->attach_to($this->client);
    }

    /** @test */
    public function test_getPath()
    {
        $request = new Request('GET', 'http://google.com');

        $m = new ReflectionMethod($this->recorder, 'getPath');
        $m->setAccessible(true);

        $this->assertSame(__DIR__ . '/responses/get/google.com/', $m->invoke($this->recorder, $request));
    }

    /** @test */
    public function test_getFileName()
    {
        $request = new Request('GET', 'http://google.com', [
            'myheader' => 'myvalue',
            'Cookie' => 'foo'
        ]);

        $m = new ReflectionMethod($this->recorder, 'getFileName');
        $m->setAccessible(true);

        $this->assertSame('f7c1e7964485802fe8842827a8ad7823.txt', $m->invoke($this->recorder, $request));
    }

    /** @test */
    public function test_getFileName_excludes_cookies()
    {
        $this->recorder->includeCookies(false);

        $request1 = new Request('GET', 'http://google.com', [
            'myheader' => 'myvalue',
            'Cookie' => 'foo'
        ]);

        $request2 = new Request('GET', 'http://google.com', [
            'myheader' => 'myvalue'
        ]);

        $m = new ReflectionMethod($this->recorder, 'getFileName');
        $m->setAccessible(true);

        $this->assertSame('0ec133b60aaa14f35d2185c09e590a48.txt', $m->invoke($this->recorder, $request1));
        $this->assertSame('0ec133b60aaa14f35d2185c09e590a48.txt', $m->invoke($this->recorder, $request2));
    }

}
