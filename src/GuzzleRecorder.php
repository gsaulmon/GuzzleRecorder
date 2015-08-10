<?php namespace Gsaulmon\GuzzleRecorder;

use GuzzleHttp\Event\BeforeEvent;
use GuzzleHttp\Event\CompleteEvent;
use GuzzleHttp\Event\RequestEvents;
use GuzzleHttp\Event\SubscriberInterface;
use GuzzleHttp\Message\MessageFactory;
use GuzzleHttp\Message\Request;

class GuzzleRecorder implements SubscriberInterface
{
    private $path;
    private $include_cookies = true;
    private $ignored_headers = array();

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function getIgnoredHeaders()
    {
        return array_values($this->ignored_headers);
    }

    public function addIgnoredHeader($name)
    {
        $this->ignored_headers[strtoupper($name)] = $name;
        return $this;
    }

    public function includeCookies($boolean)
    {
        $this->include_cookies = $boolean;
        if ($boolean) {
            $this->addIgnoredHeader('Cookie');
        } else {
            if (array_key_exists('COOKIE', $this->ignored_headers)) {
                unset($this->ignored_headers['COOKIE']);
            }
        }
        return $this;
    }

    public function getEvents()
    {
        return [
            'before' => array('onBefore', RequestEvents::LATE),
            'complete' => array('onComplete'),
        ];
    }

    public function onBefore(BeforeEvent $event)
    {
        $request = $event->getRequest();

        if (file_exists($this->getFullFilePath($request))) {
            $responsedata = file_get_contents($this->getFullFilePath($request));
            $mf = new MessageFactory();
            $event->intercept($mf->fromMessage($responsedata));
        }
    }

    public function onComplete(CompleteEvent $event)
    {
        $request = $event->getRequest();

        if (!file_exists($this->getPath($request))) {
            mkdir($this->getPath($request), 0777, true);
        }

        $response = $event->getResponse();

        file_put_contents($this->getFullFilePath($request), (string)$response);
    }

    protected function getPath(Request $request)
    {
        $path = $this->path . DIRECTORY_SEPARATOR . strtolower($request->getMethod()) . DIRECTORY_SEPARATOR . $request->getHost() . DIRECTORY_SEPARATOR;

        if ($request->getPath() !== '/') {
            $rpath = $request->getPath();
            $rpath = (substr($rpath, 0, 1) === '/') ? substr($rpath, 1) : $rpath;
            $rpath = (substr($rpath, -1, 1) === '/') ? substr($rpath, 0, -1) : $rpath;

            $path .= str_replace("/", "_", $rpath) . DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    protected function getFileName(Request $request)
    {
        $result = trim($request->getMethod() . ' ' . $request->getResource())
            . ' HTTP/' . $request->getProtocolVersion();
        foreach ($request->getHeaders() as $name => $values) {
            if (array_key_exists(strtoupper($name), $this->ignored_headers)) {
                continue;
            }
            $result .= "\r\n{$name}: " . implode(', ', $values);
        }

        $request = $result . "\r\n\r\n" . $request->getBody();
        return md5((string)$request) . ".txt";
    }

    protected function getFullFilePath(Request $request)
    {
        return $this->getPath($request) . $this->getFileName($request);
    }
}