<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2026 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */


declare(strict_types=1);

namespace App\Message\WLED;

/**
 * Messenger message that instructs the worker to turn off a set of WLED segments.
 *
 * Carries device/segment data so the handler needs no database access.
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
