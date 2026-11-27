<?php

namespace Cronitor;

class Transport
{
    public $dropped = 0;

    private $send;
    private $maxQueueSize;
    private $queue = [];
    private $rateLimitedUntil = 0.0;
    private $reportedRateLimit = false;
    private $reportedMissingDsn = false;
    private $reportedInvalidDsn = false;
    private $shutdownRegistered = false;

    public function __construct($send = null, $maxQueueSize = Ingest::MAX_QUEUE_SIZE)
    {
        $this->send = is_callable($send) ? $send : [$this, 'httpSend'];
        $this->maxQueueSize = $maxQueueSize;
    }

    public function submitEvent(array $event, $dsn = null)
    {
        $resolved = $this->resolveDsn($dsn);
        if ($resolved === null) {
            return false;
        }

        if (count($this->queue) >= $this->maxQueueSize) {
            $this->dropped += 1;
            if ($this->dropped === 1 || $this->dropped % 1000 === 0) {
                \error_log(
                    sprintf('Cronitor ingest queue is full, %d item(s) dropped so far.', $this->dropped),
                    0
                );
            }
            return false;
        }

        try {
            $payload = Ingest::eventBytes($event);
            $envelope = Ingest::eventEnvelope($payload, $event['event_id'], null, $resolved['publicDsn']);
            $this->queue[] = [
                'url' => $resolved['url'],
                'ingestKey' => $resolved['ingestKey'],
                'body' => $envelope,
            ];
            $this->registerShutdown();
            return true;
        } catch (\Throwable $e) {
            \error_log('Cronitor could not queue an event: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function flush($timeout = 2.0)
    {
        try {
            if (empty($this->queue)) {
                return true;
            }
            $timeout = (float) $timeout;
            $deadline = microtime(true) + max(0.0, $timeout);

            while (!empty($this->queue)) {
                $remaining = $deadline - microtime(true);
                if ($timeout > 0.0 && $remaining <= 0.0) {
                    return false;
                }
                $item = array_shift($this->queue);
                $requestTimeout = $timeout > 0.0
                    ? min((float) Ingest::SEND_TIMEOUT, $remaining)
                    : (float) Ingest::SEND_TIMEOUT;
                $this->deliver($item['url'], $item['ingestKey'], $item['body'], $requestTimeout);
            }
            return true;
        } catch (\Throwable $e) {
            \error_log('Cronitor flush failed: ' . $e->getMessage(), 0);
            return false;
        }
    }

    public function shutdownFlush()
    {
        $this->flush(2.0);
    }

    public function pendingCount()
    {
        return count($this->queue);
    }

    private function registerShutdown()
    {
        if (!$this->shutdownRegistered) {
            $this->shutdownRegistered = true;
            register_shutdown_function([$this, 'shutdownFlush']);
        }
    }

    private function resolveDsn($dsn)
    {
        if ($dsn === null || $dsn === '') {
            if (!$this->reportedMissingDsn) {
                $this->reportedMissingDsn = true;
                \error_log(
                    'No DSN detected. Set dsn or CRONITOR_DSN to capture exceptions and messages.',
                    0
                );
            }
            return null;
        }

        $parsed = Ingest::parseDsn($dsn);
        if ($parsed === null) {
            if (!$this->reportedInvalidDsn) {
                $this->reportedInvalidDsn = true;
                \error_log(
                    'Invalid Cronitor DSN. Expected https://<ingest_key>@cronitor.link/1.',
                    0
                );
            }
            return null;
        }

        return $parsed;
    }

    private function deliver($url, $ingestKey, $body, $timeout)
    {
        $left = $this->rateLimitedUntil - microtime(true);
        if ($left > 0.0) {
            $this->dropped += 1;
            if (!$this->reportedRateLimit) {
                $this->reportedRateLimit = true;
                \error_log(
                    sprintf(
                        'Cronitor is rate limited for %.0f more seconds; dropping envelopes until then.',
                        ceil($left)
                    ),
                    0
                );
            }
            return;
        }

        $compressed = gzencode($body, 6);
        if ($compressed === false) {
            \error_log('Cronitor could not compress an envelope.', 0);
            return;
        }

        try {
            $response = call_user_func(
                $this->send,
                $url,
                $compressed,
                Ingest::requestHeaders($ingestKey),
                $timeout
            );
        } catch (\Throwable $e) {
            \error_log(sprintf('Cronitor could not send an envelope to %s: %s', $url, $e->getMessage()), 0);
            return;
        }

        if (!is_array($response) || !isset($response['code']) || !is_int($response['code'])) {
            return;
        }

        $status = $response['code'];
        if ($status === 429) {
            $delay = $this->retryAfter(isset($response['headers']) ? $response['headers'] : []);
            $this->rateLimitedUntil = microtime(true) + $delay;
            $this->reportedRateLimit = false;
            \error_log(
                sprintf(
                    'Cronitor rate limited the envelope (HTTP 429); not sending for %.0f seconds.',
                    $delay
                ),
                0
            );
        } elseif ($status >= 400) {
            \error_log(sprintf('Cronitor rejected an envelope with HTTP %d.', $status), 0);
        }
    }

    private function retryAfter($headers)
    {
        $normalized = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $normalized[strtolower((string) $k)] = $v;
            }
        }

        $delay = null;
        if (isset($normalized['x-sentry-rate-limits']) && is_string($normalized['x-sentry-rate-limits'])) {
            $firstEntry = explode(',', $normalized['x-sentry-rate-limits'])[0];
            $firstField = explode(':', $firstEntry)[0];
            $delay = $this->parseSeconds($firstField);
        }
        if ($delay === null && isset($normalized['retry-after'])) {
            $delay = $this->parseSeconds($normalized['retry-after']);
        }
        if ($delay === null) {
            $delay = Ingest::MAX_RETRY_AFTER;
        }

        return max(0.0, min($delay, Ingest::MAX_RETRY_AFTER));
    }

    private function parseSeconds($value)
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $seconds = (float) $value;
        return is_finite($seconds) ? $seconds : null;
    }

    private function httpSend($url, $data, array $headers, $timeout)
    {
        $formattedHeaders = [];
        foreach ($headers as $key => $value) {
            $formattedHeaders[] = "$key: $value";
        }

        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_TIMEOUT_MS => max(1, (int) ceil($timeout * 1000)),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => false,
            CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($headerLine);
            },
        ]);

        $content = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = null;
        if ($content === false) {
            $error = curl_error($ch);
            \error_log(sprintf('Cronitor could not send an envelope to %s: %s', $url, $error), 0);
        }
        curl_close($ch);

        return [
            'code' => $code,
            'headers' => $responseHeaders,
            'error' => $error,
        ];
    }
}
