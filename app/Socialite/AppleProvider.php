<?php

namespace App\Socialite;

use GuzzleHttp\RequestOptions;
use SocialiteProviders\Apple\Provider as BaseProvider;

class AppleProvider extends BaseProvider
{
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            RequestOptions::FORM_PARAMS => $this->getTokenFields($code),
        ]);

        return json_decode((string) $response->getBody(), true);
    }
}
