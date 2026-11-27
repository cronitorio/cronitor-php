<?php

namespace Cronitor\Tests;

use AspectMock\Test as test;
use Cronitor\Client;
use Cronitor\ErrorApp;
use Cronitor\ErrorAppNotFound;
use Cronitor\ErrorGroup;
use Cronitor\ErrorGroupNotFound;
use Cronitor\Ingest;
use Cronitor\Monitor;
use Cronitor\Transport;

final class ErrorTrackingTest extends TestBase
{
    private function decodeEnvelope($gzippedBody)
    {
        $raw = gzdecode($gzippedBody);
        $this->assertNotFalse($raw);
        $lines = explode("\n", rtrim($raw, "\n"));
        $this->assertGreaterThanOrEqual(3, count($lines));

        $envelopeHeader = json_decode($lines[0], true);
        $itemHeader = json_decode($lines[1], true);
        $payloadRaw = $lines[2];
        $payload = json_decode($payloadRaw, true);

        return [
            'envelopeHeader' => $envelopeHeader,
            'itemHeader' => $itemHeader,
            'payloadRaw' => $payloadRaw,
            'payload' => $payload,
        ];
    }

    private function makeRecorderClient($dsn = 'https://ing_abc123@cronitor.link/1', array $options = [], $statusCode = 200, array $responseHeaders = [])
    {
        $recorded = new \ArrayObject();
        $transport = new Transport(function ($url, $body, $headers, $timeout) use ($recorded, $statusCode, $responseHeaders) {
            $recorded->append([
                'url' => $url,
                'body' => $body,
                'headers' => $headers,
                'timeout' => $timeout,
            ]);
            return ['code' => $statusCode, 'headers' => $responseHeaders, 'error' => null];
        });

        $config = array_merge([
            'apiVersion' => '2020-10-01',
            'environment' => 'production',
            'dsn' => $dsn,
            'release' => '2.4.1',
        ], $options);

        $client = new Client('api_secret_key', $config);
        $client->setTransport($transport);

        return [$client, $recorded];
    }

    public function testParseDsnValidFormats()
    {
        $std = Ingest::parseDsn('https://ing_abc123@cronitor.link/1');
        $this->assertSame([
            'ingestKey' => 'ing_abc123',
            'url' => 'https://cronitor.link/api/1/envelope/',
            'publicDsn' => 'https://ing_abc123@cronitor.link/1',
        ], $std);

        $trailing = Ingest::parseDsn('https://ing_abc123@cronitor.link/1/');
        $this->assertSame($std, $trailing);

        $custom = Ingest::parseDsn('http://dev_key@localhost:9100/1');
        $this->assertSame([
            'ingestKey' => 'dev_key',
            'url' => 'http://localhost:9100/api/1/envelope/',
            'publicDsn' => 'http://dev_key@localhost:9100/1',
        ], $custom);

        $withSecret = Ingest::parseDsn('https://pub_key:secret_part@cronitor.link/42');
        $this->assertSame([
            'ingestKey' => 'pub_key',
            'url' => 'https://cronitor.link/api/42/envelope/',
            'publicDsn' => 'https://pub_key@cronitor.link/42',
        ], $withSecret);

        $defaultProject = Ingest::parseDsn('https://ing_abc123@cronitor.link');
        $this->assertSame($std, $defaultProject);
    }

    public function testParseDsnInvalid()
    {
        $this->assertNull(Ingest::parseDsn(null));
        $this->assertNull(Ingest::parseDsn(''));
        $this->assertNull(Ingest::parseDsn('   '));
        $this->assertNull(Ingest::parseDsn('not-a-dsn'));
        $this->assertNull(Ingest::parseDsn('ftp://ing_abc@cronitor.link/1'));
        $this->assertNull(Ingest::parseDsn('https://cronitor.link/1'));
        $this->assertNull(Ingest::parseDsn('https://:onlysecret@cronitor.link/1'));
    }

    public function testGzippedEnvelopeHeadersAndNoApiKeyFallback()
    {
        list($client, $recorded) = $this->makeRecorderClient('https://ing_abc123:ignored@cronitor.link/1/');

        $eventId = $client->captureMessage('hello world');
        $this->assertNotNull($eventId);
        $this->assertTrue($client->flush());
        $this->assertCount(1, $recorded);

        $req = $recorded[0];
        $this->assertSame('https://cronitor.link/api/1/envelope/', $req['url']);
        $this->assertSame('application/x-sentry-envelope', $req['headers']['Content-Type']);
        $this->assertSame('gzip', $req['headers']['Content-Encoding']);
        $this->assertSame('cronitor-php', $req['headers']['User-Agent']);
        $this->assertSame(
            'Sentry sentry_key=ing_abc123, sentry_version=7, sentry_client=cronitor-php/1.2.0',
            $req['headers']['X-Sentry-Auth']
        );
        $this->assertArrayNotHasKey('Authorization', $req['headers']);

        $decoded = $this->decodeEnvelope($req['body']);
        $this->assertSame($eventId, $decoded['envelopeHeader']['event_id']);
        $this->assertSame('https://ing_abc123@cronitor.link/1', $decoded['envelopeHeader']['dsn']);
        $this->assertSame(['name' => 'cronitor-php', 'version' => '1.2.0'], $decoded['envelopeHeader']['sdk']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/', $decoded['envelopeHeader']['sent_at']);
        $this->assertSame('event', $decoded['itemHeader']['type']);
        $this->assertSame('application/json', $decoded['itemHeader']['content_type']);
        $this->assertSame(strlen($decoded['payloadRaw']), $decoded['itemHeader']['length']);

        list($noDsnClient, $noDsnRecorded) = $this->makeRecorderClient(null);
        $this->assertNull($noDsnClient->captureMessage('should not send'));
        $this->assertNull($noDsnClient->captureException(new \RuntimeException('boom')));
        $this->assertTrue($noDsnClient->flush());
        $this->assertCount(0, $noDsnRecorded);
    }

    public function testCaptureExceptionWithStackFramesAndChain()
    {
        list($client, $recorded) = $this->makeRecorderClient();

        $root = new \InvalidArgumentException('bad input');
        $outer = new \RuntimeException('wrapper failed', 0, $root);

        $eventId = $client->captureException($outer, [
            'monitor' => 'import-orders',
            'series' => '1700000000',
            'level' => 'critical',
            'tags' => ['region' => 'us-east-1'],
            'extra' => ['batch' => 42, 'bad_float' => NAN],
            'fingerprint' => ['import-orders', 'wrapper'],
        ]);

        $this->assertNotNull($eventId);
        $this->assertTrue($client->flush());
        $this->assertCount(1, $recorded);

        $event = $this->decodeEnvelope($recorded[0]['body'])['payload'];
        $this->assertSame('php', $event['platform']);
        $this->assertSame('fatal', $event['level']);
        $this->assertSame('2.4.1', $event['release']);
        $this->assertSame('production', $event['environment']);
        $this->assertSame('import-orders', $event['tags']['cronitor.monitor']);
        $this->assertSame('1700000000', $event['tags']['cronitor.series']);
        $this->assertSame('us-east-1', $event['tags']['region']);
        $this->assertSame(['import-orders', 'wrapper'], $event['fingerprint']);
        $this->assertSame(42, $event['extra']['batch']);
        $this->assertSame('NAN', $event['extra']['bad_float']);

        $values = $event['exception']['values'];
        $this->assertCount(2, $values);
        $this->assertSame('InvalidArgumentException', $values[0]['type']);
        $this->assertSame('bad input', $values[0]['value']);
        $this->assertSame('RuntimeException', $values[1]['type']);
        $this->assertSame('wrapper failed', $values[1]['value']);
        $this->assertSame(['type' => 'generic', 'handled' => true], $values[1]['mechanism']);

        $frames = $values[1]['stacktrace']['frames'];
        $this->assertNotEmpty($frames);
        $crashFrame = $frames[count($frames) - 1];
        $this->assertStringContainsString('ErrorTrackingTest.php', $crashFrame['filename']);
        $this->assertArrayHasKey('function', $crashFrame);
        $this->assertArrayHasKey('lineno', $crashFrame);
        $this->assertArrayHasKey('colno', $crashFrame);
        $this->assertArrayNotHasKey('vars', $crashFrame);
        $this->assertArrayNotHasKey('args', $crashFrame);
        $this->assertTrue($crashFrame['in_app']);
        $this->assertStringContainsString('wrapper failed', $crashFrame['context_line']);
        $this->assertCount(5, $crashFrame['pre_context']);
        $this->assertCount(5, $crashFrame['post_context']);

        $this->assertNull($client->captureException('not-a-throwable'));
        $this->assertNull($client->captureException(null));
    }

    public function testCaptureMessageAndMonitorBinding()
    {
        list($client, $recorded) = $this->makeRecorderClient();

        $eventId = $client->captureMessage('Disk 90% full', [
            'level' => 'warn',
            'tags' => ['host' => 'worker-1'],
            'extra' => ['mount' => '/var'],
            'fingerprint' => 'disk-space-warning',
        ]);
        $this->assertNotNull($eventId);

        $monitor = $client->monitor('nightly-sync');
        $boundId = $monitor->captureMessage('Sync finished with warnings');
        $this->assertNotNull($boundId);

        $standaloneMonitor = new Monitor('orphan-monitor', 'api_secret_key');
        $this->assertNull($standaloneMonitor->captureMessage('ignored'));
        $this->assertNull($standaloneMonitor->captureException(new \RuntimeException('ignored')));

        $this->assertTrue($client->flush());
        $this->assertCount(2, $recorded);

        $first = $this->decodeEnvelope($recorded[0]['body'])['payload'];
        $this->assertArrayNotHasKey('exception', $first);
        $this->assertSame(['formatted' => 'Disk 90% full'], $first['message']);
        $this->assertSame('warning', $first['level']);
        $this->assertSame(['disk-space-warning'], $first['fingerprint']);
        $this->assertSame(['mount' => '/var'], $first['extra']);

        $second = $this->decodeEnvelope($recorded[1]['body'])['payload'];
        $this->assertSame('info', $second['level']);
        $this->assertSame('nightly-sync', $second['tags']['cronitor.monitor']);
        $this->assertSame('production', $second['environment']);
    }

    public function testJobAutoCaptureOptOutAndThrowableRethrow()
    {
        test::double('Cronitor\HttpClient', ['get' => ['code' => 200, 'content' => '']]);

        list($client, $recorded) = $this->makeRecorderClient();

        try {
            $client->job('billing-job', function () {
                throw new \RuntimeException('charge failed');
            });
            $this->fail('Expected RuntimeException to be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('charge failed', $e->getMessage());
        }

        try {
            $client->job('billing-job', function () {
                throw new \TypeError('bad argument type');
            });
            $this->fail('Expected TypeError to be rethrown');
        } catch (\TypeError $e) {
            $this->assertSame('bad argument type', $e->getMessage());
        }

        try {
            $client->job('billing-job', function () {
                throw new \RuntimeException('opted out per call');
            }, ['captureExceptions' => false]);
            $this->fail('Expected RuntimeException to be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('opted out per call', $e->getMessage());
        }

        $client->captureExceptions = false;
        try {
            $client->job('billing-job', function () {
                throw new \RuntimeException('opted out globally');
            });
            $this->fail('Expected RuntimeException to be rethrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('opted out globally', $e->getMessage());
        }

        $this->assertTrue($client->flush());
        $this->assertCount(2, $recorded);

        $first = $this->decodeEnvelope($recorded[0]['body'])['payload'];
        $this->assertSame('billing-job', $first['tags']['cronitor.monitor']);
        $this->assertArrayHasKey('cronitor.series', $first['tags']);
        $this->assertSame(['type' => 'cronitor.job', 'handled' => false], $first['exception']['values'][0]['mechanism']);

        $second = $this->decodeEnvelope($recorded[1]['body'])['payload'];
        $this->assertSame('TypeError', $second['exception']['values'][0]['type']);
        $this->assertSame(['type' => 'cronitor.job', 'handled' => false], $second['exception']['values'][0]['mechanism']);
    }

    public function testJobStillCapturesAndRethrowsWhenFailPingThrows()
    {
        test::double('Cronitor\Monitor', [
            'ping' => function () {
                throw new \RuntimeException('telemetry outage');
            },
        ]);

        list($client, $recorded) = $this->makeRecorderClient();

        try {
            $client->job('resilient-job', function () {
                throw new \LogicException('original job failure');
            });
            $this->fail('Expected LogicException to be rethrown');
        } catch (\LogicException $e) {
            $this->assertSame('original job failure', $e->getMessage());
        }

        $this->assertTrue($client->flush());
        $this->assertCount(1, $recorded);
        $event = $this->decodeEnvelope($recorded[0]['body'])['payload'];
        $this->assertSame('LogicException', $event['exception']['values'][0]['type']);
    }

    public function testFlushTimeoutAndSwallowedTransportErrors()
    {
        $attempts = 0;
        $failingTransport = new Transport(function () use (&$attempts) {
            $attempts += 1;
            throw new \RuntimeException('network down');
        });

        $client = new Client('api_key', ['dsn' => 'https://ing_abc@cronitor.link/1']);
        $client->setTransport($failingTransport);

        $this->assertNotNull($client->captureMessage('will fail safely'));
        $this->assertTrue($client->flush(2.0));
        $this->assertSame(1, $attempts);

        $slowTransport = new Transport(function () {
            usleep(60000);
            return ['code' => 200, 'headers' => [], 'error' => null];
        });
        $client->setTransport($slowTransport);
        $client->captureMessage('msg 1');
        $client->captureMessage('msg 2');
        $this->assertFalse($client->flush(0.02));
        $this->assertTrue($client->flush(2.0));
    }

    public function testErrorAppRestResource()
    {
        $appPayload = [
            'key' => 'app_0123456789abcdef',
            'name' => 'billing-api',
            'dsn' => 'https://ing_old@cronitor.link/1',
        ];
        $rotatedPayload = [
            'key' => 'app_0123456789abcdef',
            'name' => 'payments-api',
            'dsn' => 'https://ing_new@cronitor.link/1',
        ];

        $listCall = 0;
        test::double('Cronitor\HttpClient', [
            'post' => function ($path) use ($appPayload, $rotatedPayload) {
                if ($path === '/rotate-key') {
                    return ['code' => 200, 'content' => json_encode($rotatedPayload)];
                }
                return ['code' => 201, 'content' => json_encode($appPayload)];
            },
            'get' => function ($path) use (&$listCall, $appPayload) {
                if (strpos($path, '?') === 0) {
                    $listCall += 1;
                    $items = $listCall === 1 ? [$appPayload] : [];
                    return ['code' => 200, 'content' => json_encode(['error_apps' => $items])];
                }
                return ['code' => 200, 'content' => json_encode($appPayload)];
            },
            'patch' => ['code' => 200, 'content' => json_encode(['key' => 'app_0123456789abcdef', 'name' => 'payments-api', 'dsn' => 'https://ing_old@cronitor.link/1'])],
            'delete' => ['code' => 204, 'content' => ''],
        ]);

        $client = new Client('api_key', '2020-10-01');
        $created = $client->errorApps->create(['name' => 'billing-api']);
        $this->assertInstanceOf(ErrorApp::class, $created);
        $this->assertSame('app_0123456789abcdef', $created->key);
        $this->assertSame('https://ing_old@cronitor.link/1', $created->dsn);

        $listed = $client->errorApps->list(['page' => 1, 'pageSize' => 1, 'autoPaginate' => true]);
        $this->assertCount(1, $listed);
        $this->assertSame(2, $listCall);

        $fetched = $client->errorApp('app_0123456789abcdef');
        $this->assertSame('billing-api', $fetched->getData()['name']);

        $fetched->update(['name' => 'payments-api']);
        $this->assertSame('payments-api', $fetched->getData()['name']);

        $fetched->rotateKey();
        $this->assertSame('https://ing_new@cronitor.link/1', $fetched->dsn);

        $this->assertTrue($fetched->delete());
    }

    public function testErrorAppNotFoundThrowsTypedException()
    {
        test::double('Cronitor\HttpClient', [
            'get' => ['code' => 404, 'content' => '{"detail":"Not found"}'],
        ]);

        $client = new Client('api_key', '2020-10-01');
        $this->expectException(ErrorAppNotFound::class);
        $client->errorApp('missing-app')->getData();
    }

    public function testErrorGroupRestResource()
    {
        $groupPayload = [
            'key' => 'err_0123456789abcdef',
            'title' => 'RuntimeException: charge failed',
            'status' => 'open',
        ];
        $resolvedPayload = [
            'key' => 'err_0123456789abcdef',
            'title' => 'RuntimeException: charge failed',
            'status' => 'resolved',
        ];
        $eventsPayload = ['data' => [['event_id' => 'evt_123']]];
        $eventPayload = ['event_id' => 'evt_123', 'level' => 'error'];

        test::double('Cronitor\HttpClient', [
            'get' => function ($path) use ($groupPayload, $eventsPayload, $eventPayload) {
                if (strpos($path, '/events/evt_123') === 0) {
                    return ['code' => 200, 'content' => json_encode($eventPayload)];
                }
                if (strpos($path, '/events?') === 0) {
                    return ['code' => 200, 'content' => json_encode($eventsPayload)];
                }
                if (strpos($path, '?') === 0) {
                    return ['code' => 200, 'content' => json_encode(['errors' => [$groupPayload]])];
                }
                return ['code' => 200, 'content' => json_encode($groupPayload)];
            },
            'patch' => ['code' => 200, 'content' => json_encode($resolvedPayload)],
        ]);

        $client = new Client('api_key', '2020-10-01');
        $groups = $client->errorGroups->list([
            'app' => 'app_0123456789abcdef',
            'env' => 'production',
            'status' => 'open',
            'level' => 'error',
        ]);
        $this->assertCount(1, $groups);
        $this->assertInstanceOf(ErrorGroup::class, $groups[0]);

        $group = $client->errorGroup('err_0123456789abcdef');
        $this->assertSame('open', $group->getData()['status']);

        $group->update(['status' => 'resolved', 'env' => 'production']);
        $this->assertSame('resolved', $group->getData()['status']);

        $events = $group->events(['env' => 'production', 'limit' => 25]);
        $this->assertSame('evt_123', $events['data'][0]['event_id']);

        $event = $group->event('evt_123');
        $this->assertSame('evt_123', $event['event_id']);
    }

    public function testErrorGroupNotFoundThrowsTypedException()
    {
        test::double('Cronitor\HttpClient', [
            'get' => ['code' => 404, 'content' => '{"detail":"Not found"}'],
        ]);

        $client = new Client('api_key', '2020-10-01');
        $this->expectException(ErrorGroupNotFound::class);
        $client->errorGroup('missing-group')->getData();
    }

    public function testErrorsMonitorTypeInYamlAndPut()
    {
        $client = new Client('api_key', '2020-10-01');
        $parsed = $client->readConfig('tests/data/errors-config.yml', true);

        $this->assertArrayHasKey('errors', $parsed);
        $this->assertCount(2, $parsed['monitors']);
        $errorMonitor = $parsed['monitors'][1];
        $this->assertSame('billing-error-spikes', $errorMonitor['key']);
        $this->assertSame('errors', $errorMonitor['type']);
        $this->assertSame('spike', $errorMonitor['trigger']);
        $this->assertSame('5m', $errorMonitor['window']);
        $this->assertSame(25, $errorMonitor['threshold']);

        $capturedBody = null;
        test::double('Cronitor\HttpClient', [
            'put' => function ($path, $body) use (&$capturedBody) {
                $capturedBody = $body;
                return [
                    'code' => 200,
                    'content' => json_encode(['monitors' => [['key' => 'new-billing-errors', 'type' => 'errors']]]),
                ];
            },
        ]);

        $monitor = $client->monitors->put([
            'type' => 'errors',
            'key' => 'new-billing-errors',
            'trigger' => 'new',
            'apps' => ['app_0123456789abcdef'],
        ]);
        $this->assertInstanceOf(Monitor::class, $monitor);
        $this->assertSame('errors', $capturedBody['monitors'][0]['type']);
    }
}
