<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Avatar;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('avatars:backfill {--force : Overwrite existing built avatars}')]
#[Description('Generate a randomized built avatar for each user that does not already have one.')]
class BackfillRandomAvatars extends Command
{
    public function handle(): int
    {
        $query = User::query();

        if (! $this->option('force')) {
            $query->whereNull('avatar_config');
        }

        $count = 0;

        $query->each(function (User $user) use (&$count) {
            $user->update(['avatar_config' => Avatar::randomConfig()]);
            $count++;
        });

        $this->info("Generated {$count} avatar(s).");

        return self::SUCCESS;
    }
}
