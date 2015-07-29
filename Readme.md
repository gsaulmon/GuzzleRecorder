GuzzleRecorder
==============

This is an event listener plugin for Guzzle 4/5 aimed at setting up tests for complex responses a bit quicker.

### Usage

Attach an instance of the recorder to any Guzzle Client

```php
$client = new GuzzleHttp\Client();

$watcher = new Gsaulmon\GuzzleRecorder\GuzzleRecorder(__DIR__ . '/my_test_responses');
$watcher->attach_to($client);

```

Then run your tests. The recorder will grab and store all Guzzle responses in the "my_test_responses" folder.
All subsequent runs of the tests will have the Guzzle requests intercepted and will be injected with the stored response.


*The responses store will be the full actual response. Make sure to edit out any information you may not want to share.*