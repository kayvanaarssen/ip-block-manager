<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpLookupService
{
    /**
     * Look up geolocation data for an IP address using ip-api.com.
     *
     * Returns an array with country, countryCode, city, region, isp, org, as fields
     * or null if the lookup fails.
     *
     * Rate limit: 45 requests/minute (free tier, no API key needed).
     */
    public function lookup(string $ip): ?array
    {
        // Strip CIDR notation for lookup (use base IP)
        $lookupIp = str_contains($ip, '/') ? explode('/', $ip)[0] : $ip;

        // Skip private/reserved IPs
        if (! filter_var($lookupIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$lookupIp}", [
                'fields' => 'status,country,countryCode,regionName,city,isp,org,as',
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (($data['status'] ?? '') === 'success') {
                    return [
                        'country' => $data['country'] ?? null,
                        'country_code' => $data['countryCode'] ?? null,
                        'region' => $data['regionName'] ?? null,
                        'city' => $data['city'] ?? null,
                        'isp' => $data['isp'] ?? null,
                        'org' => $data['org'] ?? null,
                        'as' => $data['as'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::warning("IP lookup failed for {$ip}: {$e->getMessage()}");
        }

        return null;
    }
}
