<?php

declare(strict_types=1);

namespace HeyQuarry\ShopifyProfileSync;

final class Sync
{
    public const DEFAULT_PROFILE_URL = 'https://www.heyquarry.com/api/shopify/shops/profile';
    public const DEFAULT_ADMIN_API_VERSION = '2026-01';

    /** @param array{api_key?: string, profile_url?: string, admin_api_version?: string} $options */
    public static function fromSession(string $shopDomain, string $accessToken, array $options = []): SyncResult
    {
        $resolved = self::resolveOptions($options);
        $shop = self::fetchShopFromSession(
            $shopDomain,
            $accessToken,
            $options['admin_api_version'] ?? self::DEFAULT_ADMIN_API_VERSION,
        );
        $payload = ShopQuery::mapShopToPayload($shop);
        if ($payload === null) {
            return self::skipped('Shop profile missing myshopifyDomain');
        }

        return self::postProfile($resolved['profile_url'], $resolved['api_key'], $payload);
    }

    /** @param array{api_key?: string, profile_url?: string} $options */
    public static function postProfileToHeyQuarry(array $payload, array $options = []): SyncResult
    {
        if (empty($payload['shopDomain'])) {
            return self::skipped('shopDomain is required');
        }

        $resolved = self::resolveOptions($options);

        return self::postProfile($resolved['profile_url'], $resolved['api_key'], $payload);
    }

    /** @param array{api_key?: string, profile_url?: string} $options */
    private static function resolveOptions(array $options): array
    {
        $apiKey = $options['api_key'] ?? getenv('HEYQUARRY_APP_API_KEY') ?: null;
        if ($apiKey === null || trim($apiKey) === '') {
            throw new \InvalidArgumentException(
                'HeyQuarry app API key is required. Pass api_key or set HEYQUARRY_APP_API_KEY.',
            );
        }

        $profileUrl = $options['profile_url']
            ?? getenv('HEYQUARRY_PROFILE_URL')
            ?: self::DEFAULT_PROFILE_URL;

        return [
            'api_key' => trim($apiKey),
            'profile_url' => rtrim($profileUrl, '/'),
        ];
    }

    /** @return array<string, mixed>|null */
    private static function fetchShopFromSession(
        string $shopDomain,
        string $accessToken,
        string $adminApiVersion,
    ): ?array {
        $host = preg_replace('#^https?://#', '', rtrim($shopDomain, '/')) ?? $shopDomain;
        $url = "https://{$host}/admin/api/{$adminApiVersion}/graphql.json";

        $response = self::httpPost(
            $url,
            ['Content-Type: application/json', "X-Shopify-Access-Token: {$accessToken}"],
            json_encode(['query' => ShopQuery::SHOP_PROFILE_QUERY], JSON_THROW_ON_ERROR),
        );

        if ($response['body'] === null) {
            return null;
        }

        $json = json_decode($response['body'], true);

        return is_array($json) ? ($json['data']['shop'] ?? null) : null;
    }

    /** @param array<string, mixed> $payload */
    private static function postProfile(string $profileUrl, string $apiKey, array $payload): SyncResult
    {
        $result = self::httpPost(
            $profileUrl,
            ['Content-Type: application/json', "X-App-Api-Key: {$apiKey}"],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        if ($result['status'] >= 200 && $result['status'] < 300) {
            return new SyncResult(
                ok: true,
                shopDomain: $payload['shopDomain'],
                status: $result['status'],
            );
        }

        return new SyncResult(
            ok: false,
            shopDomain: $payload['shopDomain'],
            status: $result['status'] ?: null,
            error: $result['body'] !== null ? substr($result['body'], 0, 300) : 'Request failed',
        );
    }

    private static function skipped(string $message): SyncResult
    {
        return new SyncResult(ok: false, skipped: true, error: $message);
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string|null}
     */
    private static function httpPost(string $url, array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return [
                'status' => $status,
                'body' => is_string($response) ? $response : null,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', (string) $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [
            'status' => $status,
            'body' => is_string($response) ? $response : null,
        ];
    }
}
