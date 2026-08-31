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
use App\Services\WLED\WledService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/locate')]
final class LocateController extends AbstractController
{
    private const PARAM_IP = 'WLED IP';
    private const PARAM_INDICES = 'WLED Indices';

    #[Route(path: '/lot/{lotId}', name: 'locate_lot', requirements: ['lotId' => '\d+'])]
    public function locate_by_lot(
        int $lotId,
        Request $request,
        EntityManagerInterface $em,
        WledService $wled,
    ): Response {
        $lot = $em->find(PartLot::class, $lotId);

        if ($lot instanceof PartLot) {
            $this->denyAccessUnlessGranted('read', $lot->getPart());
        } else {
            $this->addFlash('error', 'Part lot not found.');
            return $this->redirectToRoute('homepage');
        }

        $location = $lot->getStorageLocation();

        if ($location instanceof StorageLocation) {
            $this->sendLocation($location, $wled, on: $request->query->has('reset') === false);
        } else {
            $this->addFlash('error', 'This lot has no storage location.');
        }

        return $this->redirectToRoute('part_info', [
            'id' => $lot->getPart()->getID(),
            'highlightLot' => $lot->getID(),
        ]);
    }

    #[Route(path: '/storage_location/{locationId}', name: 'locate_storage_location', requirements: ['locationId' => '\d+'])]
    public function locate_by_storage_location(
        int $locationId,
        Request $request,
        EntityManagerInterface $em,
        WledService $wled,
    ): Response {
        $location = $em->find(StorageLocation::class, $locationId);

        if ($location instanceof StorageLocation) {
            $this->denyAccessUnlessGranted('read', $location);
            $this->sendLocation($location, $wled, on: $request->query->has('reset') === false);
        } else {
            $this->addFlash('error', 'Storage location not found.');
        }

        return $this->redirectToRoute('part_list_store_location', ['id' => $locationId]);
    }

    #[Route(path: '/reset_all', name: 'reset_all')]
    public function resetAll(
        EntityManagerInterface $em,
        WledService $wled,
    ): Response {
        //$this->denyAccessUnlessGranted('@tools.reel_calculator');

        $devices = [];

        foreach ($em->getRepository(StorageLocation::class)->findAll() as $location) {
            if (!$location instanceof StorageLocation) {
                continue;
            }

            foreach ($location->getParameters() as $param) {
                if ($param->getName() === self::PARAM_IP) {
                    $wledIp = trim($param->getValueText());
                    if ($wledIp !== '') {
                        $wledIps[$wledIp] = true;
                    }
                    break;
                }
            }
        }

        $statusCodes = $wled->resetAll(array_keys($wledIps));
        $failedDevices = array_keys(array_filter(
            $statusCodes,
            static fn(int $statusCode): bool => $statusCode < 200 || $statusCode >= 300
        ));

        if ($failedDevices !== []) {
            $this->addFlash(
                'error',
                sprintf('Could not reset the WLED device(s): %s.', implode(', ', $failedDevices))
            );
        }

        return $this->redirectToRoute('homepage');
    }

    /**
     * Send an on or off command for one storage location.
     */
    private function sendLocation(
        StorageLocation $location,
        WledService $wled,
        bool $on,
    ): void {
        $wledIp = null;
        $ledMin = null;
        $ledMax = null;

        foreach ($location->getParameters() as $param) {
            switch ($param->getName()) {
                case self::PARAM_IP:
                    $wledIp = trim($param->getValueText());
                    break;

                case self::PARAM_INDICES:
                    if ($param->getValueTypical() !== null) {
                        $ledMin = (int) $param->getValueTypical();
                        $ledMax = $ledMin + 1;
                    }
                    if ($param->getValueMin() !== null) {
                        $ledMin = (int) $param->getValueMin();
                    }
                    if ($param->getValueMax() !== null) {
                        $ledMax = (int) $param->getValueMax() + 1;
                    }
                    break;
            }
        }

        if ($wledIp === null || $wledIp === '') {
            $this->addFlash(
                'error',
                sprintf('Storage location "%s" has no "%s" parameter.', $location->getName(), self::PARAM_IP)
            );
            return;
        }
        if ($ledMin === null || $ledMax === null) {
            $this->addFlash(
                'error',
                sprintf('Storage location "%s" has no usable "%s" parameter.', $location->getName(), self::PARAM_INDICES)
            );
            return;
        }

        $devices = [];
        $wled->addRange($devices, $wledIp, $ledMin, $ledMax);

        $statusCodes = $wled->send($devices, $on);
        $failedDevices = array_keys(array_filter(
            $statusCodes,
            static fn(int $statusCode): bool => $statusCode < 200 || $statusCode >= 300
        ));

        if ($failedDevices !== []) {
            $this->addFlash(
                'error',
                sprintf('Could not %s the WLED device(s): %s.', $on ? 'highlight' : 'reset', implode(', ', $failedDevices))
            );
        }
    }
}
