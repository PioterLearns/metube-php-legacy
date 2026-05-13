<?php

namespace Youtube;

use Exception;

readonly class Filter
{
    public function __construct(
        private string $phrase,
        private string $filterType,
        private string $action,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function filter(string $title, string $description, string $channel): ?string
    {
        $check = match ($this->filterType) {
            'title' => $title,
            'descr' => $description,
            default => throw new Exception("Unknown filter $this->filterType"),
        };

        if (preg_match("/$this->phrase/i", $check)) {
            echo "$channel - $title filtered as: $this->action" . PHP_EOL;
            return self::actionToType($this->action);
        }

        return null;
    }

    private static function actionToType(string $action): ?string
    {
        return match ($action) {
            'i', 'I' => 'ignor',
            's', 'S' => 'solo',
            'p', 'P' => 'pcast',
            'x', 'X' => 'solo',
            'k', 'K' => 'skip',
            default => null
        };
    }
}