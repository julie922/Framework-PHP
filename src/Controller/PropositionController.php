<?php

namespace App\Controller;

use App\Entity\Proposition;
use App\Entity\User;
use App\Repository\DemandeRepository;
use App\Repository\PropositionRepository;
use App\Security\ResourceVoter;
use App\Service\PropositionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
#[IsGranted('ROLE_USER')]
class PropositionController extends AbstractController
{
    public function __construct(
        private readonly PropositionRepository $propositionRepository,
        private readonly DemandeRepository     $demandeRepository,
        private readonly PropositionService    $propositionService,
        private readonly ValidatorInterface    $validator,
    ) {}

    #[Route('/demandes/{id}/propositions', methods: ['POST'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function create(string $id, Request $request): JsonResponse
    {
        $demande = $this->demandeRepository->find($id);
        if (!$demande) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Demande $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'price'     => [new Assert\NotBlank(), new Assert\Positive()],
            'message'   => [new Assert\NotBlank(), new Assert\Length(min: 10)],
            'serviceId' => new Assert\Optional([new Assert\Uuid()]),
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $proposition = $this->propositionService->create($user, $demande, $data);
        } catch (\LogicException $e) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->propositionService->serialize($proposition), Response::HTTP_CREATED);
    }

    #[Route('/propositions', methods: ['GET'])]
    public function index(): JsonResponse
    {
        /** @var User $user */
        $user        = $this->getUser();
        $propositions = $this->propositionRepository->findByUser($user);

        return new JsonResponse(array_map([$this->propositionService, 'serialize'], $propositions));
    }

    #[Route('/propositions/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $proposition = $this->propositionRepository->find($id);
        if (!$proposition) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Proposition $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::VIEW, $proposition);

        return new JsonResponse($this->propositionService->serialize($proposition));
    }

    #[Route('/propositions/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $proposition = $this->propositionRepository->find($id);
        if (!$proposition) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Proposition $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::EDIT, $proposition);

        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'status' => [
                new Assert\NotBlank(),
                new Assert\Choice(choices: [
                    Proposition::STATUS_ACCEPTEE,
                    Proposition::STATUS_REFUSEE,
                    Proposition::STATUS_ANNULEE,
                ]),
            ],
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        /** @var User $user */
        $user = $this->getUser();

        try {
            $proposition = $this->propositionService->updateStatus($proposition, $data['status'], $user);
        } catch (\LogicException|\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->propositionService->serialize($proposition));
    }

    private function validationError(mixed $violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $v) {
            $errors[] = ['field' => $v->getPropertyPath(), 'message' => $v->getMessage()];
        }
        return new JsonResponse(['error' => 'Validation Error', 'violations' => $errors], Response::HTTP_BAD_REQUEST);
    }
}
