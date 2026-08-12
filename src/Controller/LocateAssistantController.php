<?php
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Parts\PartLot;
use App\Message\WLED\WledOffMessage;
use App\Services\WLED\WledService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/locate')]
final class LocateAssistantController extends AbstractController
{
    private const PARAM_IP = 'WLED IP';
    private const PARAM_INDICES = 'WLED Indices';

    #[Route(path: '/{lotId}', name: 'locate_assistant', methods: ['POST'], requirements: ['lotId' => '\d+'])]
    public function locate(
        int $lotId,
        Request $request,
        EntityManagerInterface $em,
        WledService $wled,
        MessageBusInterface $bus,
    ): Response {
        // Reuse an existing tool permission until a dedicated one is added
        // to the permission tree.
        $this->denyAccessUnlessGranted('@tools.reel_calculator');

        $lot = $em->find(PartLot::class, $lotId);

        if (!$lot instanceof PartLot) {
            $this->addFlash('error', 'Part lot not found.');
            return $this->redirectToReferrer($request);
        }

        $this->denyAccessUnlessGranted('read', $lot->getPart());

        $location = $lot->getStorageLocation();

        if ($location === null) {
            $this->addFlash('error', 'This lot has no storage location.');
            return $this->redirectToReferrer($request);
        }

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
        $failedDevices = array_keys(array_filter(
            $statusCodes,
            static fn(int $statusCode): bool => $statusCode < 200 || $statusCode >= 300
        ));

        if ($failedDevices !== []) {
            $this->addFlash(
                'error',
                sprintf('Could not highlight the WLED device(s): %s.', implode(', ', $failedDevices))
            );
            return $this->redirectToReferrer($request);
        }

        // Schedule the "off" command. The delay is in milliseconds.
        $bus->dispatch(
            new WledOffMessage($devices),
            [new DelayStamp(WledOffMessage::HIGHLIGHT_DURATION_S * 1000)]
        );

        return $this->redirectToReferrer($request);
    }

    private function redirectToReferrer(Request $request): Response
    {
        $referrer = $request->headers->get('referer');

        return $this->redirect($referrer ?: '/');
    }
}
