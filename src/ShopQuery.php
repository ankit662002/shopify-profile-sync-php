<?php

declare(strict_types=1);

namespace HeyQuarry\ShopifyProfileSync;

final class ShopQuery
{
    public const SHOP_PROFILE_QUERY = <<<'GRAPHQL'
query HeyQuarryShopProfile {
  shop {
    id
    name
    email
    contactEmail
    myshopifyDomain
    description
    plan { displayName }
    billingAddress { phone countryCodeV2 }
    primaryDomain { host }
  }
}
GRAPHQL;

    /** @param array<string, mixed>|null $shop */
    public static function mapShopToPayload(?array $shop): ?array
    {
        if ($shop === null || empty($shop['myshopifyDomain'])) {
            return null;
        }

        $email = trim((string) ($shop['email'] ?? ''));
        if ($email === '') {
            $email = trim((string) ($shop['contactEmail'] ?? ''));
        }

        $payload = ['shopDomain' => $shop['myshopifyDomain']];
        if ($email !== '') {
            $payload['email'] = $email;
        }
        if (!empty($shop['billingAddress']['phone'])) {
            $payload['phone'] = $shop['billingAddress']['phone'];
        }
        if (!empty($shop['plan']['displayName'])) {
            $payload['shopifyPlan'] = $shop['plan']['displayName'];
        }
        if (!empty($shop['billingAddress']['countryCodeV2'])) {
            $payload['countryCode'] = $shop['billingAddress']['countryCodeV2'];
        }
        if (!empty($shop['primaryDomain']['host'])) {
            $payload['customDomain'] = $shop['primaryDomain']['host'];
        }
        if (!empty($shop['description'])) {
            $payload['about'] = $shop['description'];
        }
        if (!empty($shop['id'])) {
            $payload['shopGid'] = $shop['id'];
        }
        if (!empty($shop['name'])) {
            $payload['shopName'] = $shop['name'];
        }

        return $payload;
    }
}
