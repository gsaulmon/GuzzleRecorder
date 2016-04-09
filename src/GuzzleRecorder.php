<?php namespace Gsaulmon\GuzzleRecorder;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Http\Message\RequestInterface;

class GuzzleRecorder
{
    private $path;
    private $include_cookies = true;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function includeCookies($boolean)
    {
        $this->include_cookies = $boolean;
        return $this;
    }

    public function attach_to(Client $client)
    {
        /** @var HandlerStack $stack */
        $stack = $client->getConfig('handler');
        $stack->push($this->onBefore());
        $stack->push($this->onComplete());
    }


    public function onBefore()
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                $promise = $handler($request, $options);

                if (file_exists($this->getFullFilePath($request))) {
                    $responsedata = file_get_contents($this->getFullFilePath($request));
                    $response = \GuzzleHttp\Psr7\parse_response($responsedata);
                    $promise->resolve($response);

                }

                return $promise;
            };
        };
    }

    public function onComplete()
    {
        return function (callable $handler) {
            return function ($request, array $options) use ($handler) {
                return $handler($request, $options)->then(
                    function ($response) use ($request) {
                        if (!file_exists($this->getPath($request))) {
                            mkdir($this->getPath($request), 0777, true);
                        }

                        if(!file_exists($this->getFullFilePath($request))) {
                            file_put_contents($this->getFullFilePath($request), \GuzzleHttp\Psr7\str($response));
                        }

                        return $response;
                    }
                );
            };
        };
    }

    protected function getPath(RequestInterface $request)
    {
        $path = $this->path . DIRECTORY_SEPARATOR . strtolower($request->getMethod()) . DIRECTORY_SEPARATOR . $request->getUri()->getHost() . DIRECTORY_SEPARATOR;

        if ($request->getRequestTarget() !== '/') {
            $rpath = $request->getUri()->getPath();
            $rpath = (substr($rpath, 0, 1) === '/') ? substr($rpath, 1) : $rpath;
            $rpath = (substr($rpath, -1, 1) === '/') ? substr($rpath, 0, -1) : $rpath;

            $path .= str_replace("/", "_", $rpath) . DIRECTORY_SEPARATOR;
        }

        return $path;
    }

    protected function getFileName(RequestInterface $request)
    {
        $result = trim($request->getMethod() . ' ' . $request->getRequestTarget())
            . ' HTTP/' . $request->getProtocolVersion();
        foreach ($request->getHeaders() as $name => $values) {
            if ($name != "Cookie" or $this->include_cookies) {
                $result .= "\r\n{$name}: " . implode(', ', $values);
            }
        }

        $request = $result . "\r\n\r\n" . $request->getBody();
        return md5((string)$request) . ".txt";
    }

    protected function getFullFilePath(RequestInterface $request)
    {
        return $this->getPath($request) . $this->getFileName($request);
    }
}