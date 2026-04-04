<?php

namespace App\Console\Commands;

use App\Models\BlockedIp;
use App\Services\IpLookupService;
use Illuminate\Console\Command;

class BackfillGeoData extends Command
{
    protected $signature = 'blocked-ips:backfill-geo';

    protected $description = 'Backfill geo location data for blocked IPs that don\'t have it yet';

    public function handle(IpLookupService $ipLookup): int
    {
        $ips = BlockedIp::whereNull('geo_data')->get();

        if ($ips->isEmpty()) {
            $this->info('All blocked IPs already have geo data.');
            return 0;
        }

        $this->info("Backfilling geo data for {$ips->count()} IP(s)...");

        $bar = $this->output->createProgressBar($ips->count());

        foreach ($ips as $ip) {
            $geoData = $ipLookup->lookup($ip->ip_address);

            if ($geoData) {
                $ip->update(['geo_data' => $geoData]);
                $this->line(" <info>{$ip->ip_address}</info> - {$geoData['country']} / {$geoData['org']}");
            } else {
                $this->line(" <comment>{$ip->ip_address}</comment> - lookup failed (private IP or API error)");
            }

            $bar->advance();

            // Respect ip-api.com rate limit (45/min)
            usleep(1500000); // 1.5 seconds between requests
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done!');

        return 0;
    }
}
