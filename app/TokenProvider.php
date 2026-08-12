<?php

namespace App;

use CleaniqueCoders\Traitify\Concerns\InteractsWithEnum;
use CleaniqueCoders\Traitify\Contracts\Enum as Contract;

enum TokenProvider: string implements Contract
{
    use InteractsWithEnum;

    case Google = 'google';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google Calendar',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Google => 'OAuth credentials for Google Calendar.',
        };
    }
}
