<?php namespace Gsaulmon\GuzzleRecorder;

use GuzzleHttp\Psr7;
use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Message\RequestInterface;
use GuzzleHttp\Promise\RejectedPromise;

class GuzzleRecorder
{
    private $path;
    private $include_cookies = true;
    private $ignored_headers = array();
    public $lastRequest = null;
    public $lastOptions = null;
    public $history = array();

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


    public function __invoke(RequestInterface $request, array $options)
    {
        if (isset($options['delay'])) {
            usleep($options['delay'] * 1000);
        }

        $this->lastRequest = $request;
        $this->lastOptions = $options;

        if (file_exists($this->getFullFilePath($request))) {
            $responseData = file_get_contents($this->getFullFilePath($request));

            $response = Psr7\parse_response($responseData);
        } else {
            $curlHandler = new CurlHandler();
            $response = $curlHandler->__invoke($request, $options);
        }

        $response = $response instanceof \Exception ? new RejectedPromise($response) : \GuzzleHttp\Promise\promise_for($response);

        return $response->then(
            function ($value) use ($request, $options) {
                // record the response
                $this->record($request, $value);

                return $value;
            },
            function ($reason) use ($request, $options) {
                // record the response
                $this->record($request, $reason);

                return $reason;
        });
    }

    public function record($request, $response)
    {
        $this->history[] = $response;

        if (!file_exists($this->getPath($request))) {
            mkdir($this->getPath($request), 0777, true);

            file_put_contents($this->getFullFilePath($request), (string)$response);
        }
    }
}