<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

final class IpAllowlist
{
    /**
     * Checks if an IP is allowed by a comma-separated list of IPs or CIDRs.
     */
    public static function matches(string $ip, string $allowlist): bool
    {
        $list = array_filter(array_map('trim', explode(',', $allowlist)));
        if (empty($list)) {
            return true;
        }

        foreach ($list as $entry) {
            if ($entry === '*' || $entry === $ip) {
                return true;
            }

            if (str_contains($entry, '/')) {
                if (self::cidrMatch($ip, $entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = ~((1 << (32 - $mask)) - 1);
            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        return false;
    }
}
