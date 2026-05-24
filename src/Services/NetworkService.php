<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\Services;

use Throwable;

use function fclose;
use function fsockopen;
use function fwrite;
use function is_resource;
use function preg_match_all;
use function sprintf;
use function stream_get_contents;
use function stream_set_timeout;

/**
 * Query WHOIS servers to retrieve the IPv4/IPv6 prefixes announced by an ASN.
 *
 * Usage:
 *   $ranges = (new NetworkService())->findIpRangesForAsn('AS15169');
 *
 * Returns a list of CIDR strings, e.g. ['8.8.8.0/24', '8.8.4.0/24', ...].
 * Returns an empty array when neither WHOIS server responds or has data.
 */
class NetworkService
{
    private const WHOIS_HOSTS           = ['whois.radb.net', 'whois.ripe.net'];
    private const WHOIS_QUERY_FORMAT    = "-i origin %s\r\n";
    private const WHOIS_TIMEOUT_SECONDS = 5;

    /**
     * Fetch all route/route6 prefixes for the given ASN from the first
     * responding WHOIS server.
     *
     * @param string $asn  e.g. "AS15169" or "15169"
     *
     * @return list<string>  CIDR strings
     */
    public function findIpRangesForAsn(string $asn): array
    {
        $query = sprintf(self::WHOIS_QUERY_FORMAT, $asn);

        foreach (self::WHOIS_HOSTS as $host) {
            $stream = false;

            try {
                $stream = fsockopen(
                    $host,
                    43,
                    $error_code,
                    $error_message,
                    self::WHOIS_TIMEOUT_SECONDS
                );

                stream_set_timeout($stream, self::WHOIS_TIMEOUT_SECONDS);
                fwrite($stream, $query);

                $text = stream_get_contents($stream);
            } catch (Throwable) {
                continue;
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            // Match both IPv4 (route:) and IPv6 (route6:) entries
            preg_match_all('/\nroute6?:[ \t]*([0-9a-f.:]+\/[0-9]+)/i', $text, $matches);

            if ($matches[1] !== []) {
                return $matches[1];
            }
        }

        return [];
    }
}
