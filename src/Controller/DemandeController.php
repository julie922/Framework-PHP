<?php

namespace App\Controller;

use App\Controller\Traits\ValidationTrait;
use App\Entity\Demande;
use App\Entity\User;
use App\Repository\DemandeRepository;
use App\Security\ResourceVoter;
use App\Service\DemandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/demandes')]
#[IsGranted('ROLE_USER')]
class DemandeController extends AbstractController
{
    use ValidationTrait;

    public function __construct(
        private readonly DemandeRepository  $demandeRepository,
        private readonly DemandeService     $demandeService,
        private readonly ValidatorInterface  $validator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $filters = array_filter([
            'mine'     => $request->query->getBoolean('mine') ?: null,
            'status'   => $request->query->get('status'),
            'category' => $request->query->get('category'),
        ]);

        $demandes = $this->demandeRepository->findWithFilters($user, $filters);

        return new JsonResponse(array_map(fn($d) => $this->demandeService->serialize($d), $demandes));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $demande = $this->demandeRepository->find($id);
        if (!$demande) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Demande $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->demandeService->serialize($demande, true));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'title'       => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            'description' => [new Assert\NotBlank()],
            'category'    => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'budget'      => [new Assert\NotBlank(), new Assert\Positive()],
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        /** @var User $user */
        $user    = $this->getUser();
        $demande = $this->demandeService->create($user, $data);

        return new JsonResponse($this->demandeService->serialize($demande), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $demande = $this->demandeRepository->find($id);
        if (!$demande) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Demande $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::EDIT, $demande);

        $data       = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($data, new Assert\Collection([
            'title'       => new Assert\Optional([new Assert\Length(max: 255)]),
            'description' => new Assert\Optional(),
            'category'    => new Assert\Optional([new Assert\Length(max: 100)]),
            'budget'      => new Assert\Optional([new Assert\Positive()]),
            'status'      => new Assert\Optional([
                new Assert\Choice(choices: [Demande::STATUS_OUVERTE, Demande::STATUS_EN_COURS, Demande::STATUS_VALIDEE, Demande::STATUS_FERMEE])
            ]),
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        try {
            $demande = $this->demandeService->update($demande, $data);
        } catch (\LogicException $e) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->demandeService->serialize($demande));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $demande = $this->demandeRepository->find($id);
        if (!$demande) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Demande $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::DELETE, $demande);
        $this->demandeService->delete($demande);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

}
