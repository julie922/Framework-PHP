<?php

namespace App\Controller;

use App\Controller\Traits\ValidationTrait;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/users')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    use ValidationTrait;

    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface          $validator,
    ) {}

    /**
     * GET /api/users/me — Mon profil complet
     */
    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return new JsonResponse($this->serializeUser($user));
    }

    /**
     * PUT /api/users/me — Modifier mon profil
     */
    #[Route('/me', methods: ['PUT'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $fields = array_filter([
            'firstName' => isset($data['firstName']) ? new Assert\Optional([new Assert\Length(min: 2, max: 100)]) : null,
            'lastName'  => isset($data['lastName'])  ? new Assert\Optional([new Assert\Length(min: 2, max: 100)]) : null,
            'password'  => isset($data['password'])  ? new Assert\Optional([new Assert\Length(min: 8), new Assert\Regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/')]) : null,
        ]);

        if (!empty($fields)) {
            $violations = $this->validator->validate($data, new Assert\Collection(array_filter($fields)));
            if (count($violations)) {
                return $this->validationError($violations);
            }
        }

        if (isset($data['firstName'])) $user->setFirstName($data['firstName']);
        if (isset($data['lastName']))  $user->setLastName($data['lastName']);
        if (isset($data['password']))  $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $this->em->flush();

        return new JsonResponse($this->serializeUser($user));
    }

    /**
     * GET /api/users/{id} — Profil public d'un autre utilisateur
     */
    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Not Found', 'message' => "Utilisateur $id introuvable."], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id'        => $user->getId(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
