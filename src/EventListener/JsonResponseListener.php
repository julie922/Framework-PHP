<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Force toutes les JsonResponse à retourner du vrai UTF-8 au lieu de \uXXXX.
 * Exemple : "annulée" au lieu de "annulée".
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
class JsonResponseListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions(
                ($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                & ~JSON_HEX_TAG    // retire les encodages HTML superflus en mode API pure
            );
        }
    }
}
