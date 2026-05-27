<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\User;
use App\Repository\ServiceRepository;
use App\Security\ResourceVoter;
use App\Service\ServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/services')]
class ServiceController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly ServiceService    $serviceService,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $filters = array_filter([
            'category' => $request->query->get('category'),
            'status'   => $request->query->get('status'),
            'search'   => $request->query->get('search'),
            'priceMax' => $request->query->get('priceMax'),
            'priceMin' => $request->query->get('priceMin'),
        ]);

        $services = $this->serviceRepository->findWithFilters($filters);

        return new JsonResponse(array_map([$this->serviceService, 'serialize'], $services));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Service $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse($this->serviceService->serialize($service));
    }

    #[Route('', methods: ['POST'])]
    #[IsGranted('ROLE_PRESTATAIRE')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'title'       => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            'description' => [new Assert\NotBlank()],
            'category'    => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'price'       => [new Assert\NotBlank(), new Assert\Positive()],
            'status'      => new Assert\Optional([new Assert\Choice(choices: [Service::STATUS_ACTIVE, Service::STATUS_INACTIVE])]),
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        /** @var User $user */
        $user    = $this->getUser();
        $service = $this->serviceService->create($user, $data);

        return new JsonResponse($this->serviceService->serialize($service), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function update(string $id, Request $request): JsonResponse
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Service $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::EDIT, $service);

        $data       = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($data, new Assert\Collection([
            'title'       => new Assert\Optional([new Assert\Length(max: 255)]),
            'description' => new Assert\Optional(),
            'category'    => new Assert\Optional([new Assert\Length(max: 100)]),
            'price'       => new Assert\Optional([new Assert\Positive()]),
            'status'      => new Assert\Optional([new Assert\Choice(choices: [Service::STATUS_ACTIVE, Service::STATUS_INACTIVE, Service::STATUS_ARCHIVED])]),
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        try {
            $service = $this->serviceService->update($service, $data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($this->serviceService->serialize($service));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id): JsonResponse
    {
        $service = $this->serviceRepository->find($id);
        if (!$service) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Service $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(ResourceVoter::DELETE, $service);
        $this->serviceService->delete($service);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
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
