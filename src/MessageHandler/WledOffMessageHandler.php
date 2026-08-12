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

namespace App\MessageHandler\WLED;

use App\Message\WLED\WledOffMessage;
use App\Services\WLED\WledService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class WledOffMessageHandler
{
    public function __construct(private readonly WledService $wled) {}

    public function __invoke(WledOffMessage $message): void
    {
        // Ignore the result — if the device is unreachable at this point
        // there is nothing useful to do, and the LEDs will time out on their
        // own or be overwritten by the next job.
        $this->wled->send($message->devices, on: false);
    }
}
