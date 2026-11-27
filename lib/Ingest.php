<?php

namespace Cronitor;

class Ingest
{
    const SDK_NAME = 'cronitor-php';
    const SDK_VERSION = '1.2.0';

    const MAX_QUEUE_SIZE = 1000;
    const SEND_TIMEOUT = 5;
    const MAX_RETRY_AFTER = 60.0;
    const MAX_EVENT_BYTES = 921600; // 900 * 1024
    const TRIMMED_FRAMES = 20;
    const EVENT_CONTENT_TYPE = 'application/json';

    public static function parseDsn($dsn)
    {
        if (!is_string($dsn)) {
            return null;
        }
        $raw = trim($dsn);
        if ($raw === '') {
            return null;
        }

        $parsed = parse_url($raw);
        if ($parsed === false) {
            return null;
        }

        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
        $host = isset($parsed['host']) ? $parsed['host'] : '';
        $user = isset($parsed['user']) ? $parsed['user'] : '';

        if (($scheme !== 'http' && $scheme !== 'https') || $host === '' || $user === '') {
            return null;
        }

        if (strpos($host, ':') !== false && $host[0] !== '[') {
            $host = '[' . $host . ']';
        }
        $netloc = isset($parsed['port']) ? $host . ':' . $parsed['port'] : $host;

        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        $prefix = '';
        $projectId = '1';
        if ($path !== '') {
            $lastSlash = strrpos($path, '/');
            if ($lastSlash !== false) {
                $prefix = '/' . substr($path, 0, $lastSlash);
                $projectId = substr($path, $lastSlash + 1);
            } else {
                $projectId = $path;
            }
        }

        $url = "{$scheme}://{$netloc}{$prefix}/api/{$projectId}/envelope/";
        $publicDsn = "{$scheme}://{$user}@{$netloc}{$prefix}/{$projectId}";

        return [
            'ingestKey' => $user,
            'url' => $url,
            'publicDsn' => $publicDsn,
        ];
    }

    public static function sdkInfo()
    {
        return [
            'name' => self::SDK_NAME,
            'version' => self::SDK_VERSION,
        ];
    }

    public static function authHeader($ingestKey)
    {
        return sprintf(
            'Sentry sentry_key=%s, sentry_version=7, sentry_client=%s/%s',
            $ingestKey,
            self::SDK_NAME,
            self::SDK_VERSION
        );
    }

    public static function requestHeaders($ingestKey)
    {
        return [
            'Content-Type' => 'application/x-sentry-envelope',
            'Content-Encoding' => 'gzip',
            'User-Agent' => self::SDK_NAME,
            'X-Sentry-Auth' => self::authHeader($ingestKey),
        ];
    }

    public static function formatTimestamp($value = null)
    {
        $utc = new \DateTimeZone('UTC');
        if ($value instanceof \DateTimeInterface) {
            $dt = \DateTimeImmutable::createFromFormat('U.u', $value->format('U.u'), $utc);
            if ($dt === false) {
                $dt = (new \DateTimeImmutable('@' . $value->getTimestamp()))->setTimezone($utc);
            }
            return $dt->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
        }
        $dt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)), $utc);
        if ($dt === false) {
            $dt = new \DateTimeImmutable('now', $utc);
        }
        return $dt->setTimezone($utc)->format('Y-m-d\TH:i:s.u\Z');
    }

    public static function jsonBytes($obj)
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $encoded = json_encode($obj, $flags);
        if ($encoded !== false) {
            return $encoded;
        }

        $safe = self::jsonSafe($obj, 0);
        $encoded = json_encode($safe, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($encoded !== false) {
            return $encoded;
        }
        return '{}';
    }

    public static function serializeEnvelope(array $headers, array $items)
    {
        $parts = [self::jsonBytes($headers), "\n"];
        foreach ($items as $entry) {
            $itemHeaders = $entry[0];
            $payload = $entry[1];
            $itemHeaders['length'] = strlen($payload);
            $parts[] = self::jsonBytes($itemHeaders);
            $parts[] = "\n";
            $parts[] = $payload;
            $parts[] = "\n";
        }
        return implode('', $parts);
    }

    public static function eventBytes(array $event)
    {
        $payload = self::jsonBytes($event);
        if (strlen($payload) <= self::MAX_EVENT_BYTES) {
            return $payload;
        }

        try {
            $copy = $event;
            $dropped = [];
            $trims = [
                ['source context dropped', [self::class, 'dropSourceContext']],
                ['extra dropped', [self::class, 'dropExtra']],
                ['frames cut to the last ' . self::TRIMMED_FRAMES . ' per exception', [self::class, 'keepLastFrames']],
            ];

            foreach ($trims as $trim) {
                $note = $trim[0];
                $fn = $trim[1];
                if (!$fn($copy)) {
                    continue;
                }
                $dropped[] = $note;
                $extra = self::extraAsArray(isset($copy['extra']) ? $copy['extra'] : null);
                $extra['_trimmed'] = sprintf(
                    '%s: event over %d KB',
                    implode('; ', $dropped),
                    (int) (self::MAX_EVENT_BYTES / 1024)
                );
                $copy['extra'] = $extra;
                $payload = self::jsonBytes($copy);
                if (strlen($payload) <= self::MAX_EVENT_BYTES) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            \error_log('Cronitor could not trim an oversized event: ' . $e->getMessage(), 0);
        }

        return $payload;
    }

    public static function eventEnvelope($payload, $eventId, $sentAt = null, $dsn = null)
    {
        $headers = [
            'event_id' => $eventId,
            'sent_at' => self::formatTimestamp($sentAt),
            'sdk' => self::sdkInfo(),
        ];
        if ($dsn) {
            $headers['dsn'] = $dsn;
        }
        $item = [
            'type' => 'event',
            'content_type' => self::EVENT_CONTENT_TYPE,
        ];
        return self::serializeEnvelope($headers, [[$item, $payload]]);
    }

    private static function extraAsArray($extra)
    {
        if (is_array($extra)) {
            return $extra;
        }
        if ($extra instanceof \stdClass) {
            return (array) $extra;
        }
        return [];
    }

    private static function dropSourceContext(array &$event)
    {
        if (!isset($event['exception']['values']) || !is_array($event['exception']['values'])) {
            return false;
        }
        $dropped = false;
        foreach ($event['exception']['values'] as $i => $value) {
            if (!isset($value['stacktrace']['frames']) || !is_array($value['stacktrace']['frames'])) {
                continue;
            }
            foreach ($value['stacktrace']['frames'] as $j => $frame) {
                if (array_key_exists('pre_context', $frame)) {
                    unset($event['exception']['values'][$i]['stacktrace']['frames'][$j]['pre_context']);
                    $dropped = true;
                }
                if (array_key_exists('post_context', $frame)) {
                    unset($event['exception']['values'][$i]['stacktrace']['frames'][$j]['post_context']);
                    $dropped = true;
                }
            }
        }
        return $dropped;
    }

    private static function dropExtra(array &$event)
    {
        $extra = self::extraAsArray(isset($event['extra']) ? $event['extra'] : null);
        if (empty($extra)) {
            return false;
        }
        $nonTrimmed = array_diff(array_keys($extra), ['_trimmed']);
        if (empty($nonTrimmed)) {
            return false;
        }
        $event['extra'] = [];
        return true;
    }

    private static function keepLastFrames(array &$event)
    {
        if (!isset($event['exception']['values']) || !is_array($event['exception']['values'])) {
            return false;
        }
        $cut = false;
        foreach ($event['exception']['values'] as $i => $value) {
            if (!isset($value['stacktrace']['frames']) || !is_array($value['stacktrace']['frames'])) {
                continue;
            }
            $frames = $value['stacktrace']['frames'];
            if (count($frames) > self::TRIMMED_FRAMES) {
                $event['exception']['values'][$i]['stacktrace']['frames'] = array_slice($frames, -self::TRIMMED_FRAMES);
                $cut = true;
            }
        }
        return $cut;
    }

    private static function jsonSafe($value, $depth)
    {
        if (is_float($value)) {
            if (is_nan($value)) {
                return 'NAN';
            }
            if (is_infinite($value)) {
                return $value > 0 ? 'INF' : '-INF';
            }
            return $value;
        }
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (is_resource($value)) {
            return '<resource ' . get_resource_type($value) . '>';
        }
        if ($depth >= 10) {
            if (is_object($value)) {
                return '<' . get_class($value) . '>';
            }
            return '<array>';
        }
        if ($value instanceof \stdClass) {
            $out = [];
            foreach ((array) $value as $k => $v) {
                if (is_string($k) && isset($k[0]) && $k[0] === "\0") {
                    continue;
                }
                $out[$k] = self::jsonSafe($v, $depth + 1);
            }
            return (object) $out;
        }
        if (is_object($value)) {
            if ($value instanceof \JsonSerializable) {
                try {
                    return self::jsonSafe($value->jsonSerialize(), $depth + 1);
                } catch (\Throwable $e) {
                    return '<' . get_class($value) . '>';
                }
            }
            if (method_exists($value, '__toString')) {
                try {
                    return (string) $value;
                } catch (\Throwable $e) {
                    return '<' . get_class($value) . '>';
                }
            }
            return '<' . get_class($value) . '>';
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::jsonSafe($v, $depth + 1);
            }
            return $out;
        }
        return '<unrepresentable>';
    }
}
