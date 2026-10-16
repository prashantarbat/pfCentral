<?php

namespace App\Services\PfSense;

/**
 * Client for outbound REST API communication with pfSense firewalls.
 */
class PfSenseApiClient
{
    protected int $defaultConnectTimeout;
    protected int $defaultTimeout;

    public function __construct(int $defaultConnectTimeout = 2, int $defaultTimeout = 4)
    {
        $this->defaultConnectTimeout = $defaultConnectTimeout;
        $this->defaultTimeout        = $defaultTimeout;
    }

    /**
     * Executes an authenticated HTTP Basic Auth call to a pfSense REST API endpoint.
     *
     * @param string $url
     * @param string $username
     * @param string $password
     * @param int|null $connectTimeout
     * @param int|null $timeout
     * @return array
     */
    public function call(
        string $url,
        string $username,
        string $password,
        ?int $connectTimeout = null,
        ?int $timeout = null
    ): array {
        log_message('debug', "Calling pfSense API: $url");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout ?? $this->defaultConnectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout ?? $this->defaultTimeout);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            log_message('debug', "API request failed. HTTP Code: $httpCode, Error: $curlError, Response: $response");
            return ['error' => "API request failed with HTTP code: $httpCode"];
        }

        $jsonResponse = json_decode($response, true);
        if (!is_array($jsonResponse)) {
            log_message('debug', "Invalid JSON Response from pfSense API: $response");
            return ['error' => "Invalid JSON response from API"];
        }

        return $jsonResponse;
    }
}
