<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

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
