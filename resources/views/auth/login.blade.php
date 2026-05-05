<x-layouts.app title="Sign in — Life">
    <flux:card class="max-w-md mx-auto mt-12">
        <div class="text-center space-y-1">
            <flux:heading size="xl">✦ Life</flux:heading>
            <flux:text variant="subtle">A home for everything your family is up to.</flux:text>
        </div>

        <div class="mt-6 space-y-4">
            @if ($pendingCodeEmail)
                <form method="POST" action="{{ route('auth.magic.verify') }}" class="space-y-3">
                    @csrf
                    <flux:field>
                        <flux:label>Enter the 6-digit code we emailed to {{ $pendingCodeEmail }}</flux:label>
                        <flux:otp length="6" name="code" autofocus class="w-full! justify-between" />
                        @error('code')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                    <flux:button type="submit" variant="primary" class="w-full!">Sign in</flux:button>
                </form>
                <form method="POST" action="{{ route('auth.magic.request') }}">
                    @csrf
                    <input type="hidden" name="email" value="{{ $pendingCodeEmail }}">
                    <flux:button type="submit" variant="ghost" size="sm" class="w-full!">Resend code</flux:button>
                </form>
            @else
                <form method="POST" action="{{ route('auth.magic.request') }}" class="space-y-2">
                    @csrf
                    <flux:field>
                        <flux:label>Sign in with email</flux:label>
                        <flux:input
                            type="email"
                            name="email"
                            placeholder="you@example.com"
                            value="{{ old('email') }}"
                            required
                        />
                        @error('email')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                    <flux:button type="submit" variant="primary" class="w-full!">Email me a sign-in link</flux:button>
                </form>
            @endif

            <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-zinc-400">
                <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
                <span>or</span>
                <div class="flex-1 h-px bg-zinc-200 dark:bg-zinc-700"></div>
            </div>

            @if ($appleEnabled)
                <flux:button :href="url('/auth/apple/redirect')" class="w-full!">
                    Sign in with Apple
                </flux:button>
            @else
                <flux:callout color="amber" icon="exclamation-triangle">
                    <flux:callout.text>
                        Sign in with Apple isn't configured yet. Set <code class="font-mono text-xs bg-amber-100 px-1 rounded">APPLE_CLIENT_ID</code> and <code class="font-mono text-xs bg-amber-100 px-1 rounded">APPLE_CLIENT_SECRET</code> in <code class="font-mono text-xs bg-amber-100 px-1 rounded">.env</code>.
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>

        <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
            @if ($pendingHousehold)
                <flux:callout color="emerald" icon="check-circle">
                    <flux:callout.text>
                        You'll join <strong>{{ $pendingHousehold->name }}</strong> after signing in.
                    </flux:callout.text>
                    <x-slot name="actions">
                        <form method="POST" action="{{ route('login.invite.clear') }}">@csrf
                            <flux:button size="sm" variant="ghost" type="submit">Cancel</flux:button>
                        </form>
                    </x-slot>
                </flux:callout>
            @else
                <form method="POST" action="{{ route('login.invite') }}" class="space-y-2">
                    @csrf
                    <flux:field>
                        <flux:label>Have an invite code?</flux:label>
                        <div class="flex gap-2">
                            <flux:input
                                name="invite_code"
                                placeholder="ABCD1234"
                                class="font-mono uppercase"
                                maxlength="12"
                                value="{{ old('invite_code') }}"
                            />
                            <flux:button type="submit">Apply</flux:button>
                        </div>
                        @error('invite_code')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                </form>
            @endif
        </div>

        @if ($devUsers->isNotEmpty())
            <div class="mt-6">
                <flux:text size="xs" variant="subtle" class="uppercase tracking-wide mb-2 block">Dev login (local only)</flux:text>
                <div class="space-y-1">
                    @foreach ($devUsers as $u)
                        <form method="POST" action="{{ url('/dev-login/' . $u->id) }}">@csrf
                            <button class="w-full text-left px-3 py-2 rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-800 text-sm">
                                <span class="font-medium">{{ $u->name }}</span>
                                <span class="text-zinc-400">— {{ $u->household->name ?? '' }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:card>
</x-layouts.app>
