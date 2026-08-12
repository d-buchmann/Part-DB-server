<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Message\WLED;

/**
 * Messenger message that instructs the worker to turn off a set of WLED segments.
 *
 * Carries a self-contained snapshot of the WledJob's device/segment data so
 * the handler needs no database access.
 *
 * HIGHLIGHT_DURATION_S is read by the caller (LocateAssistantController) to
 * set the DelayStamp. The handler itself simply executes whenever Messenger
 * delivers the message.
 *
 * Shape of $devices:
 *   array<string, list<array{segmentId: int, ledMin: int, ledMax: int}>>
 *   Keyed by WLED base URL.
 */
final class WledOffMessage
{
    /**
     * How long (in seconds) the highlight stays on before the worker turns it off.
     */
    public const HIGHLIGHT_DURATION_S = 30;

    /**
     * @param array<string, list<array{segmentId: int, ledMin: int, ledMax: int}>> $devices
     */
    public function __construct(
        public readonly array $devices,
    ) {}
}
