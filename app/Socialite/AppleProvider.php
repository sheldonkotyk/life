<?php

namespace App\Socialite;

use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use SocialiteProviders\Apple\Provider as BaseProvider;

class AppleProvider extends BaseProvider
{
    public function getAccessTokenResponse($code)
    {
        $fields = $this->getTokenFields($code);

        Log::info('apple.token.body', [
            'fields_keys' => array_keys($fields),
            'client_id' => $fields['client_id'] ?? null,
            'redirect_uri' => $fields['redirect_uri'] ?? null,
            'grant_type' => $fields['grant_type'] ?? null,
            'code_len' => isset($fields['code']) ? strlen($fields['code']) : 0,
            'secret_len' => isset($fields['client_secret']) ? strlen($fields['client_secret']) : 0,
            'secret_head' => isset($fields['client_secret']) ? substr($fields['client_secret'], 0, 60) : null,
            'secret_tail' => isset($fields['client_secret']) ? substr($fields['client_secret'], -20) : null,
        ]);

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::FORM_PARAMS => $fields,
        ]);

        return json_decode((string) $response->getBody(), true);
    }
}
