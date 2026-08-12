<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Services\WLED;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Sends highlight and off commands to one or more WLED instances.
 *
 * The caller prepares the device/segment array from application data, then
 * uses send() to illuminate or extinguish the prepared LED ranges.
 *
 * Segments are created on the fly by this service and reused across on/off
 * calls. No segment configuration is required on the WLED device in advance.
 */
final class WledService
{
    /** Highlight colour: white. */
    private const HIGHLIGHT_COLOR = [255, 255, 255];

    /** Off colour: black. */
    private const OFF_COLOR = [0, 0, 0];

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Add a LED range to the prepared device/segment array.
     *
     * Segment IDs are assigned sequentially per device and reused by the
     * delayed off-message.
     *
     * @param array<string, list<array{segmentId: int, ledMin: int, ledMax: int}>> $devices
     */
    public function addRange(array &$devices, string $baseUrl, int $ledMin, int $ledMax): void
    {
        $segmentId = isset($devices[$baseUrl]) ? count($devices[$baseUrl]) : 0;
        $devices[$baseUrl][] = [
            'segmentId' => $segmentId,
            'ledMin'    => $ledMin,
            'ledMax'    => $ledMax,
        ];
    }

    // -------------------------------------------------------------------------
    // Send
    // -------------------------------------------------------------------------

    /**
     * Send highlight or off commands to all WLED devices in the device array.
     *
     * One HTTP request is made per unique WLED base URL.
     * Pass on: true to illuminate, on: false to extinguish.
     *
     * @return array<string, int> HTTP status code per WLED base URL. A value of
     *   0 means that no HTTP response was received because of a transport error.
     */
    public function send(array $devices, bool $on): array
    {
        $statusCodes = [];

        foreach ($devices as $baseUrl => $segments) {
            $url     = 'http://' . ltrim(rtrim($baseUrl, '/'), 'http://') . '/json/state';
            $payload = $this->buildPayload($segments, $on);

            try {
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json'    => $payload,
                    'timeout' => 5.0,
                ]);
                $statusCodes[$baseUrl] = $response->getStatusCode();
            } catch (TransportExceptionInterface) {
                // No HTTP status code exists when the request could not be sent.
                $statusCodes[$baseUrl] = 0;
            }
        }

        return $statusCodes;
    }

    // -------------------------------------------------------------------------
    // Payload builder
    // -------------------------------------------------------------------------

    /**
     * Build the /json/state payload for one WLED device.
     *
     * @param list<array{segmentId: int, ledMin: int, ledMax: int}> $segments
     */
    private function buildPayload(array $segments, bool $on): array
    {
        $segPayloads = [];

        foreach ($segments as $s) {
            $segPayloads[] = [
                'id'    => $s['segmentId'],
                'start' => $s['ledMin'],
                'stop'  => $s['ledMax'] + 1, // WLED stop is exclusive
                'on'    => true,              // segment always active; colour controls visibility
                'col'   => [$on ? self::HIGHLIGHT_COLOR : self::OFF_COLOR],
                'fx'    => 0,                 // solid effect
                'bri'   => 100,
            ];
        }

        return ['seg' => $segPayloads];
    }
}
