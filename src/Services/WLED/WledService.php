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

    public function __construct(private readonly HttpClientInterface $httpClient) {}

    /**
     * Add a LED range to the prepared device/segment array.
     *
     * Segment 0 is reserved for the strip-wide background. Highlight segment
     * IDs are assigned sequentially per device starting at 1.
     *
     * @param array<string, list<array{segmentId: int, ledMin: int, ledMax: int}>> $devices
     */
    public function addRange(array &$devices, string $baseUrl, int $ledMin, int $ledMax): void
    {
        $segmentId = isset($devices[$baseUrl]) ? count($devices[$baseUrl]) + 1 : 1;
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
            $url     = 'http://' . ltrim(rtrim($baseUrl, '/'), 'https://') . '/json/state';
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

    /**
     * Remove all non-background segments from each WLED device.
     *
     * Segment 0 is configured by the user and is deliberately not included in
     * the reset payload. The current segment list is read from /json/state so
     * only segments that currently exist are invalidated.
     *
     * @param list<string> $baseUrls
     * @return array<string, int> HTTP status code per WLED base URL. A value of
     *   0 means that the state could not be queried or updated.
     */
    public function resetAll(array $baseUrls): array
    {
        $statusCodes = [];

        foreach ($baseUrls as $baseUrl) {
            $baseUrl = 'http://' . ltrim(rtrim($baseUrl, '/'), 'https://');

            try {
                $stateResponse = $this->httpClient->request('GET', $baseUrl . '/json/state', [
                    'timeout' => 5.0,
                ]);
                $state = $stateResponse->toArray();
                $segments = $state['seg'] ?? null;

                if ($stateResponse->getStatusCode() < 200
                    || $stateResponse->getStatusCode() >= 300
                    || !is_array($segments)
                ) {
                    $statusCodes[$baseUrl] = 0;
                    continue;
                }

                $resetSegments = [];
                foreach ($segments as $segment) {
                    if (!is_array($segment) || !isset($segment['id'])) {
                        continue;
                    }

                    $segmentId = (int) $segment['id'];
                    if ($segmentId === 0) {
                        continue;
                    }

                    $resetSegments[] = [
                        'id'    => $segmentId,
                        'start' => 0,
                        'stop'  => 0,
                        'on'    => true,
                    ];
                }

                if ($resetSegments === []) {
                    $statusCodes[$baseUrl] = $stateResponse->getStatusCode();
                    continue;
                }

                $response = $this->httpClient->request('POST', $baseUrl . '/json/state', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => ['seg' => $resetSegments],
                    'timeout' => 5.0,
                ]);
                $statusCodes[$baseUrl] = $response->getStatusCode();
            } catch (TransportExceptionInterface) {
                // No HTTP status code exists when either request could not be sent.
                $statusCodes[$baseUrl] = 0;
            } catch (\Throwable) {
                // The state response was not valid or did not contain a usable segment list.
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
                'stop'  => $on ? $s['ledMax'] + 1 : $s['ledMin'], // equal bounds remove the highlight segment
                'on'    => true,
                'col'   => [self::HIGHLIGHT_COLOR],
                'fx'    => 0,                 // solid effect
                'bri'   => $on ? 255 : 0,
            ];
        }

        return ['seg' => $segPayloads];
    }
}

