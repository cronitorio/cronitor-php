# Cronitor PHP Library

[Cronitor](https://cronitor.io/) provides dead simple monitoring for cron jobs, daemons, data pipelines, queue workers, and anything else that can send or receive an HTTP request. The Cronitor Ruby library provides convenient access to the Cronitor API from applications written in Ruby.

## Documentation

See our [API docs](https://cronitor.io/docs/api) for a detailed reference information about the APIs this library uses for configuring monitors and sending telemetry pings.

## Installation

```bash
composer require cronitor/cronitor-php
```

To use manually, you can include the `init.php` file from source.

```php
require_once('/path/to/cronitor-php/init.php');
```

## Usage

The package needs to be configured with your account's `API key`, which is available on the [account settings](https://cronitor.io/settings) page. You can also optionally specify an `API Version` (default: account default) and `Environment` (default: account default).

These can be supplied using the environment variables `CRONITOR_API_KEY`, `CRONITOR_API_VERSION`, `CRONITOR_ENVIRONMENT` or set directly on the cronitor object.

```php
$apiKey = 'apiKey123';
$apiVersion = '2020-10-01';
$environment = 'staging';
$cronitor = new Cronitor\Client($apiKey, $apiVersion, $environment);
```

You can also use a YAML config file to manage all of your monitors (_see Create and Update Monitors section below_). The path to this file can be supplied using the enviroment variable `CRONITOR_CONFIG` or call `$cronitor->readConfig()`.

```php
$cronitor->readConfig('./path/to/cronitor.yaml');
```

### Monitor Any Block

The quickest way to start using this library is to wrap a block of code with the `#job` helper. It will report the start time, end time, and exit state to Cronitor. If an exception is raised, the stack trace will be included in the failure message.

```php
$closureVar = time();
$cronitor->job('warehouse-replenishmenth-report', function() use ($closureVar){
  new ReplenishmentReport($closureVar)->run();
});
```

### Sending Telemetry Events

If you want finer control over when/how [telemetry pings](https://cronitor.io/docs/telemetry-api) are sent,
you can instantiate a monitor and call `#ping`.

```php
$monitor = $cronitor->monitor('heartbeat-monitor');

$monitor->ping(); # a basic heartbeat event

# optional params can be passed as kwargs
# complete list - https://cronitor.io/docs/telemetry-api#parameters

$monitor->ping(['state' => 'run', 'env' => 'staging']); # a job/process has started in a staging environment

# a job/process has completed - include metrics for cronitor to record
$monitor->ping(['state' => 'complete', 'metrics' => ['count' => 1000, 'error_count' => 17]);
```

### Pause, Reset, Delete

```php
require 'cronitor'

$monitor = $cronitor->monitor('heartbeat-monitor');

$monitor->pause(24) # pause alerting for 24 hours
$monitor->unpause() # alias for .pause(0)
$monitor->ok() # manually reset to a passing state alias for $monitor->ping({state: ok})
$monitor->delete() # destroy the monitor
```

## Create and Update Monitors

You can create monitors programatically.
For details on all of the attributes that can be set see the [Monitor API](https://cronitor.io/docs/monitor-api) documentation.

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
    'type' => 'synthetic',
    'key' => 'Orders Api Uptime',
    'schedule' => 'every 45 seconds',
    'assertions' => [
        'response.code = 200',
        'response.time < 1.5s',
        'response.json "open_orders" < 2000'
    ]
  ]
])
```

You can also manage all of your monitors via a YAML config file.
This can be version controlled and synced to Cronitor as part of
a deployment process or system update.

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

The `cronitor.yaml` file accepts the following attributes:

```yaml
api_key: "optionally read Cronitor api_key from here"
api_version: "optionally read Cronitor api_version from here"
environment: "optionally set an environment for telemetry pings"

# configure all of your monitors with type "job"
# you may omit the type attribute and the key
# of each object will be set as the monitor key
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

# configure all of your monitors with type "synthetic"
synthetics:
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

events:
  production-deploy:
    notify:
      alerts: ["deploys-slack"]
      events: true # send alert when the event occurs
```

## Contributing

Pull requests and features are happily considered! By participating in this project you agree to abide by the [Code of Conduct](http://contributor-covenant.org/version/2/0).

### To contribute

Fork, then clone the repo:

    git clone git@github.com:your-username/cronitor-php.git

Push to your fork and [submit a pull request](https://github.com/cronitorio/cronitor-php/compare/)
