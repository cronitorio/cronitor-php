# Cronitor PHP Library

![Test](https://github.com/cronitorio/cronitor-php/workflows/Test/badge.svg)

[Cronitor](https://cronitor.io/) provides end-to-end monitoring for background jobs, websites, APIs, and anything else that can send or receive an HTTP request. This library provides convenient access to the Cronitor API from applications written in PHP. See our [API docs](https://cronitor.io/docs/api) for detailed references on configuring monitors and sending telemetry pings.


In this guide:

- [Installation](#Installation)
- [Monitoring Background Jobs](#monitoring-background-jobs)
- [Sending Telemetry Events](#sending-telemetry-events)
- [Configuring Monitors](#configuring-monitors)
- [Package Configuration & Env Vars](#package-configuration)

## Installation

```bash
composer require cronitor/cronitor-php
```

To use manually, you can include the `init.php` file from source.

```php
require_once('/path/to/cronitor-php/init.php');
```

### Monitoring Background Jobs

The `$cronitor->job` function will send telemetry events before calling your function and after it exits. If your function raises an exception a `fail` event will be sent (and the exception re-raised).


```php
$cronitor = new Cronitor\Client('api_key_123');

$closureVar = time();
$cronitor->job('warehouse-replenishmenth-report', function() use ($closureVar){
  new ReplenishmentReport($closureVar)->run();
});
```

## Sending Telemetry Events

If you want to send a heartbeat events, or want finer control over when/how [telemetry events](https://cronitor.io/docs/telemetry-api) are sent for your jobs, you can create a `Monitor` instance and call the `.ping` method.


```php
$cronitor = new Cronitor\Client('api_key_123');

$monitor = $cronitor->monitor('heartbeat-monitor');

$monitor->ping(); # a basic heartbeat event

# optional params can be passed as kwargs
# complete list - https://cronitor.io/docs/telemetry-api#parameters

$monitor->ping(['state' => 'run']); # a job/process has started

# a job/process has completed (include metrics for Cronitor to record)
$monitor->ping(['state' => 'complete', 'metrics' => ['count' => 1000, 'error_count' => 17]);
```

## Configuring Monitors

You can configure all of your monitors using a single YAML file. This can be version controlled and synced to Cronitor as part of
a deployment or build process. For details on all of the attributes that can be set, see the [Monitor API](https://cronitor.io/docs/monitor-api) documentation.

```php
# read config file and set credentials (if included).
$cronitor->readConfig('./cronitor.yaml');

# sync config file's monitors to Cronitor.
$cronitor->applyConfig();

# send config file's monitors to Cronitor to validate correctness.
# monitors will not be saved.
$cronitor->validateConfig();

# save config to local YAML file (defaults to cronitor.yaml)
$cronitor->generateConfig();
```

The `cronitor.yaml` file includes three top level keys `jobs`, `checks`, `heartbeats`. You can configure monitors under each key by declaring a monitor `key` and defining [Monitor attributes](https://cronitor.io/docs/monitor-api#attributes)

```yaml
jobs:
  nightly-database-backup:
    schedule: 0 0 * * *
    notify:
      - devops-alert-pagerduty
    assertions:
      - metric.duration < 5 minutes

  send-welcome-email:
    schedule: every 10 minutes
    assertions:
      - metric.count > 0
      - metric.duration < 30 seconds

checks:
  cronitor-homepage:
    request:
      url: https://cronitor.io
      regions:
        - us-east-1
        - eu-central-1
        - ap-northeast-1
    assertions:
      - response.code = 200
      - response.time < 2s

  cronitor-telemetry-api:
    request:
      url: https://cronitor.link/ping
    assertions:
      - response.body contains ok
      - response.time < .25s

heartbeats:
  production-deploy:
    notify:
      alerts: ["deploys-slack"]
      events: true # send alert when the event occurs
```

You can also create and update monitors by calling `$cronitor->monitors->put`. For details on all of the attributes that can be set see the Monitor API [documentation)(https://cronitor.io/docs/monitor-api#attributes).

```php
$cronitor->monitors->put([
  [
    'type' => 'job',
    'key' => 'send-customer-invoices',
    'schedule' => '0 0 * * *',
    'assertions' => [
        'metric.duration < 5 min'
    ],
    'notify' => ['devops-alerts-slack']
  ],
  [
    'type' => 'check',
    'key' => 'Cronitor Homepage',
    'schedule' => 'every 45 seconds',
    'request' => [
        'url' => 'https://cronitor.io'
    ]
    'assertions' => [
        'response.code = 200',
        'response.time < 1.5s',
        'response.json "open_orders" < 2000'
    ]
  ]
])
```

### Pause, Reset, Delete

```php
require 'cronitor'

$monitor = $cronitor->monitor('heartbeat-monitor');

$monitor->pause(24) # pause alerting for 24 hours
$monitor->unpause() # alias for ->pause(0)
$monitor->ok() # manually reset to a passing state alias for $monitor->ping({state: ok})
$monitor->delete() # destroy the monitor
```

## Package Configuration

The package needs to be configured with your account's `API key`, which is available on the [account settings](https://cronitor.io/settings) page. You can also optionally specify an `api_version` and an `environment`. If not provided, your account default is used. These can also be supplied using the environment variables `CRONITOR_API_KEY`, `CRONITOR_API_VERSION`, `CRONITOR_ENVIRONMENT`.

```php
$apiKey = 'apiKey123';
$apiVersion = '2020-10-01';
$environment = 'staging';
$cronitor = new Cronitor\Client($apiKey, $apiVersion, $environment);
```

## Contributing

Pull requests and features are happily considered! By participating in this project you agree to abide by the [Code of Conduct](http://contributor-covenant.org/version/2/0).

### To contribute

Fork, then clone the repo:

    git clone git@github.com:your-username/cronitor-php.git

Push to your fork and [submit a pull request](https://github.com/cronitorio/cronitor-php/compare/)
