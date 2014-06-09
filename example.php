<?php
include 'vendor/autoload.php';

$client = new GuzzleHttp\Client();

/**
 * Create a new instance of the watcher plugin and attach it to the client
 */
$watcher = new Gsaulmon\GuzzleRecorder\GuzzleRecorder(__DIR__ . '/exampleresponses');
$client->getEmitter()->attach($watcher);

/**
 * If a request has been previously recorded, the recorded response will be return
 */
$request = $client->get('http://www.google.com/');
$b =  $request->getBody();