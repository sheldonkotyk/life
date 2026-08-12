<?php

namespace App\Models;

use App\TokenProvider;
use CleaniqueCoders\TokenVault\Enums\Type;
use CleaniqueCoders\TokenVault\Models\TokenVault;
use Database\Factories\GoogleCalendarTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GoogleCalendarToken extends TokenVault
{
    /** @use HasFactory<GoogleCalendarTokenFactory> */
    protected $table = 'token_vaults';

    protected $fillable = [
        'provider',
        'type',
        'token',
        'meta',
        'expires_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected function casts(): array
    {
        return [
            'provider' => TokenProvider::class,
            'type' => Type::class,
            'meta' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: string|null}
     */
    public function credentials(): array
    {
        $credentials = json_decode($this->getDecryptedToken(), true, flags: JSON_THROW_ON_ERROR);

        return [
            'access_token' => (string) $credentials['access_token'],
            'refresh_token' => isset($credentials['refresh_token'])
                ? (string) $credentials['refresh_token']
                : null,
        ];
    }

    /**
     * @param  list<string>  $scopes
     */
    public function setCredentials(string $accessToken, ?string $refreshToken, array $scopes): void
    {
        $this->token = json_encode([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
        ], JSON_THROW_ON_ERROR);
        $this->meta = [
            'scopes' => array_values($scopes),
            'last_four' => [
                'access_token' => mb_substr($accessToken, -4),
                'refresh_token' => $refreshToken ? mb_substr($refreshToken, -4) : null,
            ],
        ];
    }

    public function accessToken(): string
    {
        return $this->credentials()['access_token'];
    }

    public function refreshToken(): ?string
    {
        return $this->credentials()['refresh_token'];
    }

    /** @return list<string> */
    public function scopes(): array
    {
        return array_values($this->meta['scopes'] ?? []);
    }

    public function maskedAccessToken(): string
    {
        return $this->masked('access_token');
    }

    public function maskedRefreshToken(): ?string
    {
        if (blank($this->meta['last_four']['refresh_token'] ?? null)) {
            return null;
        }

        return $this->masked('refresh_token');
    }

    private function masked(string $credential): string
    {
        $lastFour = $this->meta['last_four'][$credential] ?? null;

        return $lastFour ? '••••••••'.$lastFour : '••••••••';
    }
}
