<?php

namespace App\Console\Commands;

use Firebase\JWT\JWT;
use Illuminate\Console\Command;

class AppleClientSecret extends Command
{
    protected $signature = 'apple:client-secret
        {--team= : Apple Team ID (10 chars)}
        {--key-id= : Key ID for the .p8 (10 chars)}
        {--client-id= : Services ID, e.g. com.kotyk.life.web}
        {--key-path= : Absolute path to AuthKey_XXXXXXXXXX.p8}
        {--months=6 : Lifetime in months (max 6)}';

    protected $description = 'Generate the JWT used as APPLE_CLIENT_SECRET for Sign in with Apple';

    public function handle(): int
    {
        $team = $this->option('team') ?: $this->ask('Team ID');
        $keyId = $this->option('key-id') ?: $this->ask('Key ID');
        $clientId = $this->option('client-id') ?: $this->ask('Services ID (client_id)');
        $keyPath = $this->option('key-path') ?: $this->ask('Path to .p8 file');
        $months = (int) $this->option('months');

        if (! is_readable($keyPath)) {
            $this->error("Cannot read key file: {$keyPath}");
            return self::FAILURE;
        }

        $months = max(1, min(6, $months));
        $now = time();
        $exp = strtotime("+{$months} months", $now);

        $jwt = JWT::encode(
            [
                'iss' => $team,
                'iat' => $now,
                'exp' => $exp,
                'aud' => 'https://appleid.apple.com',
                'sub' => $clientId,
            ],
            file_get_contents($keyPath),
            'ES256',
            $keyId,
        );

        $this->newLine();
        $this->line($jwt);
        $this->newLine();
        $this->info('Expires ' . date('Y-m-d H:i:s', $exp));

        return self::SUCCESS;
    }
}
