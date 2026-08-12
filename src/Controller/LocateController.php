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

namespace App\Controller;

use App\Entity\Parts\PartLot;
use App\Entity\Parts\StorageLocation;
use App\Message\WledOffMessage;
use App\Services\WLED\WledService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/locate')]
final class LocateController extends AbstractController
{
    private const PARAM_IP = 'WLED IP';
    private const PARAM_INDICES = 'WLED Indices';

    #[Route(path: '/lot/{lotId}', name: 'locate_by_lot', requirements: ['lotId' => '\d+'])]
    public function locate(
        int $lotId,
        Request $request,
        EntityManagerInterface $em,
        WledService $wled,
        MessageBusInterface $bus,
    ): Response {

        $lot = $em->find(PartLot::class, $lotId);
        
        if (!$lot instanceof PartLot) {
            $this->addFlash('error', 'Part lot with ID ' . $lotId . ' not found.');
            return $this->redirectToReferrer($request);
        }

        $this->denyAccessUnlessGranted('read', $lot->getPart());

        $location = $lot->getStorageLocation();

        if ($location === null) {
            $this->addFlash('error', 'Part lot with ID ' . $lotId . ' has no storage location.');
            return $this->redirectToReferrer($request);
        }
        return $this->locate_by_storage_location($location->getId(), $request, $em, $wled, $bus);
    }

    #[Route(path: '/storage_location/{locId}', name: 'locate_by_storage_location', requirements: ['locId' => '\d+'])]
    public function locate_by_storage_location(
        int $locId,
        Request $request,
        EntityManagerInterface $em,
        WledService $wled,
        MessageBusInterface $bus,
    ): Response {

        $this->denyAccessUnlessGranted('@storelocations.read');
        $location = $em->find(StorageLocation::class, $locId);

        $wledIp = null;
        $ledMin = null;
        $ledMax = null;

        foreach ($location->getParameters() as $param) {
            switch ($param->getName()) {
                case self::PARAM_IP:
                    $wledIp = trim($param->getValueText());
                    break;

                case self::PARAM_INDICES:
                    if ($param->getValueMin() !== null) {
                        $ledMin = (int) $param->getValueMin();
                    }
                    if ($param->getValueMax() !== null) {
                        $ledMax = (int) $param->getValueMax();
                    }
                    break;
            }
        }

        if ($wledIp === null || $wledIp === '') {
            $this->addFlash(
                'error',
                sprintf('Storage location "%s" has no "%s" parameter.', $location->getName(), self::PARAM_IP)
            );
            return $this->redirectToReferrer($request);
        }
        if ($ledMin === null || $ledMax === null) {
            $this->addFlash(
                'error',
                sprintf('Storage location "%s" has no "%s" parameter with min/max values.', $location->getName(), self::PARAM_INDICES)
            );
            return $this->redirectToReferrer($request);
        }

        $devices = [];
        $wled->addRange($devices, $wledIp, $ledMin, $ledMax);

        $statusCodes = $wled->send($devices, on: true);
        $failedDevices = array_filter(
            $statusCodes,
            static fn(int $statusCode): bool => $statusCode < 200 || $statusCode >= 300
        );

        foreach($failedDevices as $failedDevice => $statusCode) {
            $this->addFlash(
                'error',
                sprintf('Could not highlight the WLED device(s): %s (code %s).', $failedDevice, $statusCode)
            );
            return $this->redirectToReferrer($request);
        }

        // Schedule the "off" command. The delay is in milliseconds.
        $bus->dispatch(
            (new Envelope(new WledOffMessage($devices)))->with(
                new DelayStamp(WledOffMessage::HIGHLIGHT_DURATION_S * 1000)
            )
        );

        return $this->redirectToReferrer($request);
    }

    private function redirectToReferrer(Request $request): Response
    {
        $referrer = $request->headers->get('referer');

        return $this->redirect($referrer ?: '/');
    }
}
