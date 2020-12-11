<?php declare(strict_types=1);

namespace Cronitor\DTO;

class MonitorTransferObject
{
    /**
     * @var string
     */
    public string $type;

    /**
     * @var string
     */
    public string $key;

    /**
     * @var string
     */
    public string $schedule;

    /**
     * @var array|null
     */
    public ?array $notify;

    /**
     * @var array|null
     */
    public ?array $request;

    /**
     * @var array|null
     */
    public ?array $assertions;

    public function __construct(
        string $type,
        string $key,
        string $schedule,
        array $notify = null,
        array $assertions = null,
        array $request = null
    ) {
        $this->type = $type;
        $this->key = $key;
        $this->schedule = $schedule;

        $this->notify = $notify;
        $this->assertions = $assertions;
        $this->request = $request;
    }

    public static function build(
        string $type,
        string $key,
        string $schedule,
        array $notify = null,
        array $assertions = null,
        array $request = null
    ): self {
        return new self(
            $type,
            $key,
            $schedule,
            $notify,
            $assertions,
            $request
        );
    }
}