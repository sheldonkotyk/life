<?php

namespace App\Actions;

use App\Models\FamilyMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Turn a verified email address into a usable account.
 *
 * Sign-in is passwordless, so the first verified email is also the sign-up.
 * Both the web session flow and the mobile API land here, which is what keeps
 * a phone-first sign-in from producing an account the web app cannot open.
 */
class ProvisionUserForEmail
{
    public function execute(string $email, ?Household $invitedHousehold = null): User
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->name = Str::headline(Str::before($email, '@')) ?: 'You';
        }

        $newHousehold = null;
        if (! $user->household_id && ! $invitedHousehold) {
            $householdName = $user->name ? $user->name."'s Household" : 'Your Household';
            $newHousehold = Household::create(['name' => $householdName]);
        }

        $user->email_verified_at = $user->email_verified_at ?: now();
        $user->save();

        if ($newHousehold) {
            $user->joinHousehold($newHousehold);
        }

        if ($invitedHousehold) {
            $user->joinHousehold($invitedHousehold);
        }

        if (! $user->familyMember) {
            FamilyMember::create([
                'household_id' => $user->household_id,
                'user_id' => $user->id,
                'name' => $user->name,
            ]);
        }

        return $user;
    }
}
