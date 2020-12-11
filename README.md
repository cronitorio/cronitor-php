[![Build Status](https://travis-ci.org/nathanielks/cronitor-io-php.svg?branch=master)](https://travis-ci.org/nathanielks/cronitor-io-php)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nathanielks/cronitor-io-php/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nathanielks/cronitor-io-php/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/nathanielks/cronitor-io-php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/nathanielks/cronitor-io-php/?branch=master)

# Cronitor PHP SDK

Cronitor is a service for heartbeat-style monitoring of anything that can send an HTTP request. It's particularly well suited for monitoring cron jobs, Laravel scheduled tasks, or any other background task.

This library provides a simple abstraction for the pinging of a Cronitor monitor. For a better understanding of the API this library talks to, please see our [Ping API docs](https://cronitor.io/docs/ping-api). For a general introduction to Cronitor please read [How Cronitor Works](https://cronitor.io/docs/how-cronitor-works).

## Table of Contents

+ [Installation](#installation)
+ [Requirements](#requirements)
+ [Examples](#examples)

## Installation

Using [composer](https://packagist.org/packages/nathanielks/cronitor-io-php):

```bash
$ composer require cronitor/cronitor-php
```

## Requirements

The following versions of PHP are supported by this version.

+ PHP 5.4
+ PHP 5.5
+ PHP 5.6
+ PHP 7.0

## Instructions

Using this SDK library you will be able to manage and use your Cronitor Monitors easily.

The first thing you will need to do is to create the Cronitor Object:

### Using A Configuration File

This way will allow you to point to a file location and load in your typical cronitor YAML config, here is an example:

```yaml
api_key: '123456-api-key-123456'
environment: 'staging'
api_version: '2020-10-27'

```

```php
<?php declare(strict_types=1);

use Cronitor\Cronitor;

require __DIR__ . '/vendor/autoload.php';

$cronitor = Cronitor::config(__DIR__ . '/test.yml');
```

### Using the programmatic route

```php
<?php declare(strict_types=1);

use Cronitor\Cronitor;

require __DIR__ . '/vendor/autoload.php';

$cronitor = new Cronitor(
    '123456-api-key-123456', // API Key
    'staging', // Local Environment
    '2020-10-27' // API Version - this is not required
)
```

## Monitoring

Once you have your Cronitor object you will want to start working with monitors:

### Create a new Monitor

It is quite simple to create a new Monitor, all you need to do is pass through a `MonitorTransferObject` that has been built.

```php
<?php declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Cronitor\Cronitor;
use Cronitor\DTO\MonitorTransferObject;

$cronitor = Cronitor::config(__DIR__ . '/test.yml');

$monitor = $cronitor->monitor->put(
    MonitorTransferObject::build(
        'job', // Monitor Type
        '12345', // Monitor Key
        'every 5 minutes' // Schedule
    )
);
```


More documentation and examples coming soon ...