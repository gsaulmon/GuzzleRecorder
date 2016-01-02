<?php namespace Gsaulmon\GuzzleRecorder;

use GuzzleHttp\Psr7;
use Psr\Http\Message\RequestInterface;

class GuzzleRecorder
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
        if (!$boolean) {
            $this->addIgnoredHeader('Cookie');
        } else {
            if (array_key_exists('COOKIE', $this->ignored_headers)) {
                unset($this->ignored_headers['COOKIE']);
            }
        }
        return $this;
    }


    public function record() {
        return function(callable $handler) {
            return function(RequestInterface $request, array $options) use ($handler) {

                if (file_exists($this->getFullFilePath($request))) {
                    $responseData = file_get_contents($this->getFullFilePath($request));

                    $fakeResponse = Psr7\parse_response($responseData);

                    return $handler($request, $options)->resolve($fakeResponse);
                } else {
                    return $handler($request, $options)->then(function(\Psr\Http\Message\ResponseInterface $response) use ($request) {

                        if (!file_exists($this->getPath($request))) {
                            mkdir($this->getPath($request), 0777, true);
                        }

                        file_put_contents($this->getFullFilePath($request), (string)$response);
                        return $response;
                    });
                }


            };
        };
    }

    protected function getPath(RequestInterface $request)
    {
        $path = $this->path . DIRECTORY_SEPARATOR . strtolower($request->getMethod()) . DIRECTORY_SEPARATOR . $request->getUri()->getHost() . DIRECTORY_SEPARATOR;

        $rpath = $request->getUri()->getPath();

        if ($rpath && $rpath !== '/') {
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
            if (array_key_exists(strtoupper($name), $this->ignored_headers)) {
                continue;
            }
            $result .= "\r\n{$name}: " . implode(', ', $values);
        }

        $request = $result . "\r\n\r\n" . $request->getBody();
        return md5((string)$request) . ".txt";
    }

    protected function getFullFilePath(RequestInterface $request)
    {
        return $this->getPath($request) . $this->getFileName($request);
    }
}