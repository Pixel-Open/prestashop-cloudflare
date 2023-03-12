<?php
/**
 * Copyright (C) 2023 Pixel Développement
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pixel\Module\Cloudflare\Model;

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
     * @param string  $method
     * @param string  $path
     * @param mixed[] $body
     *
     * @return mixed[]
     * @throws TransportExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function execute(string $method, string $path, array $body = []): array
    {
        if ($this->config->getAuthMode() === 'api_key' && $this->config->getApiKey() && $this->config->getEmail()) {
            $headers = [
                'X-Auth-Key'   => $this->config->getApiKey(),
                'X-Auth-Email' => $this->config->getEmail()
            ];
        }

        if ($this->config->getAuthMode() === 'api_token' && $this->config->getApiToken()) {
            $headers = [
                'Authorization' => 'Bearer ' . $this->config->getApiToken()
            ];
        }

        if (empty($headers)) {
            return $this->internalError('Please set API connection parameters in module configuration');
        }

        $response = $this->client->request(
            $method,
            $this->config->getApiUrl() . $path,
            [
                'body'    => json_encode($body),
                'headers' => $headers
            ]
        );

        return json_decode($response->getContent(), true);
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
