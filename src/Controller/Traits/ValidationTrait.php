<?php

namespace App\Controller\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait partagé par tous les contrôleurs API pour formater les erreurs de validation.
 */
trait ValidationTrait
{
    private function validationError(mixed $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $v) {
            $errors[] = ['field' => $v->getPropertyPath(), 'message' => $v->getMessage()];
        }
        return new JsonResponse(['error' => 'Validation Error', 'violations' => $errors], Response::HTTP_BAD_REQUEST);
    }
}
