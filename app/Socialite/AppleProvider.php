<?php

namespace App\Socialite;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Apple\Provider as BaseProvider;

class AppleProvider extends BaseProvider
{
    public function getAccessTokenResponse($code)
    {
        $fields = $this->getTokenFields($code);
        $fields['client_secret'] = $this->getClientSecret();

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::FORM_PARAMS => $fields,
        ]);

        return json_decode((string) $response->getBody(), true);
    }
}
