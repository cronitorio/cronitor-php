<?php

namespace Cronitor;

class Capture
{
    const MONITOR_TAG = 'cronitor.monitor';
    const SERIES_TAG = 'cronitor.series';

    const MAX_VALUE_LENGTH = 4000;
    const MAX_MESSAGE_LENGTH = 4000;
    const MAX_TAGS = 50;
    const MAX_TAG_KEY_LENGTH = 64;
    const MAX_TAG_VALUE_LENGTH = 400;
    const MAX_CHAINED_EXCEPTIONS = 10;
    const MAX_FRAMES = 100;
    const MAX_LINE_LENGTH = 1000;
    const CONTEXT_LINES = 5;

    const EVENT_LEVELS = [
        'fatal' => 'fatal',
        'critical' => 'fatal',
        'error' => 'error',
        'warning' => 'warning',
        'warn' => 'warning',
        'info' => 'info',
        'debug' => 'debug',
    ];

    public static function captureException(Client $client, $throwable, array $options = [])
    {
        try {
            if (!($throwable instanceof \Throwable)) {
                return null;
            }
            $mechanism = isset($options['mechanism']) ? (string) $options['mechanism'] : 'generic';
            $handled = array_key_exists('handled', $options) ? (bool) $options['handled'] : true;

            $event = self::baseEvent($client, $options, 'error');
            $event['exception'] = [
                'values' => self::exceptionValues($throwable, $mechanism, $handled),
            ];

            return $client->getTransport()->submitEvent($event, $client->dsn) ? $event['event_id'] : null;
        } catch (\Throwable $e) {
            \error_log('Cronitor could not capture an exception: ' . $e->getMessage(), 0);
            return null;
        }
    }

    public static function captureMessage(Client $client, $message, array $options = [])
    {
        try {
            if ($message === null) {
                return null;
            }

            $event = self::baseEvent($client, $options, 'info');
            $event['message'] = [
                'formatted' => self::truncate(self::safeStr($message), self::MAX_MESSAGE_LENGTH),
            ];

            return $client->getTransport()->submitEvent($event, $client->dsn) ? $event['event_id'] : null;
        } catch (\Throwable $e) {
            \error_log('Cronitor could not capture a message: ' . $e->getMessage(), 0);
            return null;
        }
    }

    private static function baseEvent(Client $client, array $options, $defaultLevel)
    {
        $userTags = isset($options['tags']) && is_array($options['tags']) ? $options['tags'] : [];
        $monitor = array_key_exists('monitor', $options) ? $options['monitor'] : null;
        $series = array_key_exists('series', $options) ? $options['series'] : null;
        $env = array_key_exists('env', $options) ? $options['env'] : null;

        if ($monitor === null && isset($userTags[self::MONITOR_TAG])) {
            $monitor = $userTags[self::MONITOR_TAG];
        }
        if ($series === null && isset($userTags[self::SERIES_TAG])) {
            $series = $userTags[self::SERIES_TAG];
        }

        $job = $client->currentJob();
        if ($job !== null && ($monitor === null || $monitor === $job['monitor'])) {
            $monitor = $job['monitor'];
            if ($series === null) {
                $series = $job['series'];
            }
            if ($env === null) {
                $env = $job['env'];
            }
        }
        if ($env === null) {
            $env = $client->environment;
        }

        $tagEntries = [];
        if ($monitor !== null) {
            $tagEntries[] = [self::MONITOR_TAG, $monitor];
        }
        if ($series !== null) {
            $tagEntries[] = [self::SERIES_TAG, $series];
        }
        foreach ($userTags as $k => $v) {
            $tagEntries[] = [$k, $v];
        }

        $eventTags = [];
        foreach ($tagEntries as $pair) {
            $key = self::truncate(self::safeStr($pair[0]), self::MAX_TAG_KEY_LENGTH);
            if (array_key_exists($key, $eventTags) || count($eventTags) >= self::MAX_TAGS) {
                continue;
            }
            $eventTags[$key] = self::truncate(self::safeStr($pair[1]), self::MAX_TAG_VALUE_LENGTH);
        }

        $extra = isset($options['extra']) && is_array($options['extra']) ? $options['extra'] : [];

        $event = [
            'event_id' => bin2hex(random_bytes(16)),
            'timestamp' => Ingest::formatTimestamp(),
            'platform' => 'php',
            'level' => self::normalizeLevel(isset($options['level']) ? $options['level'] : null, $defaultLevel),
            'server_name' => self::hostname(),
            'tags' => empty($eventTags) ? new \stdClass() : $eventTags,
            'extra' => empty($extra) ? new \stdClass() : $extra,
            'sdk' => Ingest::sdkInfo(),
        ];

        if (isset($options['fingerprint'])) {
            if (is_string($options['fingerprint'])) {
                $event['fingerprint'] = [$options['fingerprint']];
            } elseif (is_array($options['fingerprint'])) {
                $fp = [];
                foreach ($options['fingerprint'] as $part) {
                    $fp[] = self::safeStr($part);
                }
                $event['fingerprint'] = $fp;
            }
        }

        if ($client->release !== null && $client->release !== '') {
            $event['release'] = self::safeStr($client->release);
        }
        if ($env !== null && $env !== '') {
            $event['environment'] = self::safeStr($env);
        }

        return $event;
    }

    private static function exceptionValues(\Throwable $throwable, $mechanism, $handled)
    {
        $chain = [];
        $seen = [];
        $current = $throwable;

        while ($current instanceof \Throwable && count($chain) < self::MAX_CHAINED_EXCEPTIONS) {
            $id = spl_object_id($current);
            if (isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            $chain[] = $current;
            $current = $current->getPrevious();
        }

        $chain = array_reverse($chain);
        $values = [];
        foreach ($chain as $link) {
            $values[] = [
                'type' => get_class($link),
                'value' => self::truncate(self::safeStr($link->getMessage()), self::MAX_VALUE_LENGTH),
                'mechanism' => [
                    'type' => $mechanism,
                    'handled' => $handled,
                ],
                'stacktrace' => [
                    'frames' => self::extractFrames($link),
                ],
            ];
        }

        return $values;
    }

    private static function extractFrames(\Throwable $throwable)
    {
        $trace = $throwable->getTrace();
        $rawFrames = [];

        // PHP's getTrace() places file/line on the call site frame rather than the called frame.
        $topFunction = isset($trace[0]) ? self::formatFunction($trace[0]) : '<main>';
        $rawFrames[] = [
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'function' => $topFunction,
        ];

        $count = count($trace);
        for ($i = 0; $i < $count; $i++) {
            $entry = $trace[$i];
            $nextFunction = isset($trace[$i + 1]) ? self::formatFunction($trace[$i + 1]) : '<main>';
            $rawFrames[] = [
                'file' => isset($entry['file']) ? $entry['file'] : null,
                'line' => isset($entry['line']) ? (int) $entry['line'] : null,
                'function' => $nextFunction,
            ];
        }

        $rawFrames = array_reverse($rawFrames);

        if (count($rawFrames) > self::MAX_FRAMES) {
            $half = (int) floor(self::MAX_FRAMES / 2);
            $rawFrames = array_merge(
                array_slice($rawFrames, 0, $half),
                array_slice($rawFrames, -$half)
            );
        }

        $frames = [];
        foreach ($rawFrames as $raw) {
            $frames[] = self::buildFrame($raw['file'], $raw['line'], $raw['function']);
        }
        return $frames;
    }

    private static function buildFrame($file, $lineno, $function)
    {
        $absPath = ($file !== null && $file !== '') ? $file : '[internal]';
        $context = self::sourceContext($absPath, $lineno);

        return [
            'filename' => self::displayFilename($absPath),
            'abs_path' => $absPath,
            'function' => $function,
            'lineno' => $lineno,
            'colno' => null,
            'context_line' => $context['context_line'],
            'pre_context' => $context['pre_context'],
            'post_context' => $context['post_context'],
            'in_app' => self::isInApp($absPath),
        ];
    }

    private static function formatFunction(array $frame)
    {
        $fn = isset($frame['function']) ? $frame['function'] : '<anonymous>';
        if (isset($frame['class']) && $frame['class'] !== '') {
            $type = isset($frame['type']) ? $frame['type'] : '::';
            return $frame['class'] . $type . $fn;
        }
        return $fn;
    }

    private static function displayFilename($absPath)
    {
        if ($absPath === '[internal]' || $absPath === '' || $absPath[0] === '<') {
            return $absPath;
        }
        $cwd = getcwd();
        if ($cwd !== false && $cwd !== '') {
            $prefix = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            if (strpos($absPath, $prefix) === 0) {
                return substr($absPath, strlen($prefix));
            }
        }
        return $absPath;
    }

    private static function isInApp($absPath)
    {
        if ($absPath === '' || $absPath === '[internal]' || $absPath[0] === '<') {
            return false;
        }
        if (strpos($absPath, "eval()'d code") !== false) {
            return false;
        }
        if (preg_match('/(^|[\/\\\\])vendor([\/\\\\]|$)/', $absPath) === 1) {
            return false;
        }
        return true;
    }

    private static function sourceContext($absPath, $lineno)
    {
        $empty = [
            'pre_context' => [],
            'context_line' => null,
            'post_context' => [],
        ];

        if ($absPath === '' || $absPath === '[internal]' || !$lineno || $lineno < 1) {
            return $empty;
        }
        if (!is_file($absPath) || !is_readable($absPath)) {
            return $empty;
        }

        $lines = @file($absPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lineno > count($lines)) {
            return $empty;
        }

        $index = $lineno - 1;
        $start = max(0, $index - self::CONTEXT_LINES);
        $end = min(count($lines), $index + self::CONTEXT_LINES + 1);

        $slice = [];
        for ($i = $start; $i < $end; $i++) {
            $slice[] = self::truncate($lines[$i], self::MAX_LINE_LENGTH);
        }

        $offset = $index - $start;
        return [
            'pre_context' => array_slice($slice, 0, $offset),
            'context_line' => $slice[$offset],
            'post_context' => array_slice($slice, $offset + 1),
        ];
    }

    private static function normalizeLevel($level, $fallback)
    {
        if ($level === null) {
            return $fallback;
        }
        $key = strtolower(self::safeStr($level));
        return isset(self::EVENT_LEVELS[$key]) ? self::EVENT_LEVELS[$key] : $fallback;
    }

    private static function hostname()
    {
        $host = gethostname();
        return $host !== false ? $host : '';
    }

    private static function truncate($value, $length)
    {
        return strlen($value) <= $length ? $value : substr($value, 0, $length);
    }

    private static function safeStr($value)
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            try {
                return (string) $value;
            } catch (\Throwable $e) {
                return '<unrepresentable ' . get_class($value) . '>';
            }
        }
        if (is_object($value)) {
            return '<' . get_class($value) . '>';
        }
        return '<unrepresentable ' . gettype($value) . '>';
    }
}
