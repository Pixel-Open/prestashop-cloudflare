<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model;

use Pixel\Module\Cloudflare\Exception\ApiException;
use Pixel\Module\Cloudflare\Helper\Config;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class Api
{
    /**
     * Request timeout, in seconds
     */
    const TIMEOUT = 15;

    /**
     * @var HttpClientInterface $client
     */
    private $client;

    /**
     * @var Config $config
     */
    private $config;

    /**
     * @param HttpClientInterface $client
     * @param Config $config
     */
    public function __construct(HttpClientInterface $client, Config $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * Retrieve minifier settings
     *
     * @return array
     */
    public function GetMinifySetting(): array
    {
        if (!$this->config->getZoneId()) {
            return $this->internalError('The Zone ID parameter is missing in the module configuration');
        }

        try {
            return $this->execute(
                'GET',
                'zones/' . $this->config->getZoneId() . '/settings/minify'
            );
        } catch (Throwable $throwable) {
            return $this->internalError($throwable->getMessage());
        }
    }

    /**
     * Patch Minify Setting
     * @deprecated Cloudflare had deprecated the Auto Minify feature on August 5th, 2024
     *
     * @param array $values
     * @return array
     */
    public function patchMinifySetting(array $values): array
    {
        if (!$this->config->getZoneId()) {
            return $this->internalError('The Zone ID parameter is missing in the module configuration');
        }

        $types = ['js' => 'off', 'css' => 'off', 'html' => 'off'];
        foreach ($values as $type) {
            if (!isset($types[$type])) {
                continue;
            }
            $types[$type] = 'on';
        }

        try {
            return $this->execute(
                'PATCH',
                'zones/' . $this->config->getZoneId() . '/settings/minify',
                ['value' => $types]
            );
        } catch (Throwable $throwable) {
            return $this->internalError($throwable->getMessage());
        }
    }

    /**
     * Clear the cache
     *
     * @return array
     */
    public function clearCache(): array
    {
        if (!$this->config->getZoneId()) {
            return $this->internalError('The Zone ID parameter is missing in the module configuration');
        }

        try {
            return $this->execute(
                'POST',
                'zones/' . $this->config->getZoneId() . '/purge_cache',
                ['purge_everything' => true]
            );
        } catch (Throwable $throwable) {
            return $this->internalError($throwable->getMessage());
        }
    }

    /**
     * Execute api method
     *
     * @param string $method
     * @param string $path
     * @param array  $body
     *
     * @return array
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function execute(string $method, string $path, array $body = []): array
    {
        $headers = $this->getHeaders();

        if (empty($headers)) {
            return $this->internalError('Please set API connection parameters in module configuration');
        }

        if (!empty($body)) {
            $headers['content-type'] = 'application/json';
        }

        $response = $this->client->request(
            $method,
            $this->config->getApiUrl() . $path,
            [
                'body'    => !empty($body) ? json_encode($body) : '',
                'headers' => $headers
            ]
        );

        return json_decode($response->getContent(), true);
    }

    /**
     * Send a request to the API and return the "result" part of the response
     *
     * @param string $method
     * @param string $path
     * @param array|null $body
     *
     * @return array
     * @throws ApiException
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $headers = $this->getHeaders();
        if (empty($headers)) {
            throw new ApiException('Please set API connection parameters in module configuration');
        }
        $headers['Accept'] = 'application/json';

        $options = [
            'headers' => $headers,
            'timeout' => self::TIMEOUT,
        ];
        if ($body !== null) {
            $options['headers']['content-type'] = 'application/json';
            $options['body'] = json_encode($body);
        }

        try {
            $response = $this->client->request($method, $this->config->getApiUrl() . ltrim($path, '/'), $options);
            $status = $response->getStatusCode();
            $data = json_decode($response->getContent(false), true);
        } catch (Throwable $throwable) {
            throw new ApiException('Cloudflare API request failed: ' . $throwable->getMessage(), 0, $throwable);
        }

        if (!is_array($data)) {
            throw new ApiException(sprintf('Cloudflare API error (HTTP %d): invalid response', $status), $status);
        }

        if (empty($data['success'])) {
            $errors = is_array($data['errors'] ?? null) ? $data['errors'] : [];
            $messages = [];
            $codes = [];
            foreach ($errors as $error) {
                $messages[] = sprintf('[%s] %s', $error['code'] ?? '?', $error['message'] ?? '');
                $codes[] = (int)($error['code'] ?? 0);
            }
            throw new ApiException(
                sprintf(
                    'Cloudflare API error (HTTP %d): %s',
                    $status,
                    $messages ? implode(', ', $messages) : 'invalid response'
                ),
                $status,
                null,
                $codes
            );
        }

        return is_array($data['result'] ?? null) ? $data['result'] : [];
    }

    /**
     * Send a request on the configured zone and return the "result" part of the response
     *
     * @param string $method
     * @param string $path Path relative to the zone
     * @param array|null $body
     *
     * @return array
     * @throws ApiException
     */
    public function zoneRequest(string $method, string $path, ?array $body = null): array
    {
        $zoneId = trim((string)$this->config->getZoneId());
        if ($zoneId === '') {
            throw new ApiException('The Zone ID parameter is missing in the module configuration');
        }

        return $this->request($method, 'zones/' . rawurlencode($zoneId) . '/' . ltrim($path, '/'), $body);
    }

    /**
     * Retrieve the authentication headers, according to the authentication mode
     *
     * @return string[]
     */
    protected function getHeaders(): array
    {
        if ($this->config->getAuthMode() === 'api_key' && $this->config->getApiKey() && $this->config->getEmail()) {
            return [
                'X-Auth-Key'   => $this->config->getApiKey(),
                'X-Auth-Email' => $this->config->getEmail()
            ];
        }

        if ($this->config->getAuthMode() === 'api_token' && $this->config->getApiToken()) {
            return [
                'Authorization' => 'Bearer ' . $this->config->getApiToken()
            ];
        }

        return [];
    }

    /**
     * Retrieve an internal error response
     *
     * @param string $message
     * @return array
     */
    protected function internalError(string $message): array
    {
        return [
            'result'  => null,
            'success' => false,
            'errors'  => [
                [
                    'code'    => 0,
                    'message' => $message,
                ]
            ]
        ];
    }
}
