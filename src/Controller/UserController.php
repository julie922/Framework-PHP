<?php

namespace App\Controller;

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

#[Route('/api')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository              $userRepository,
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface          $validator,
    ) {}

    #[Route('/user/profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function profile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return new JsonResponse($this->serializeUser($user));
    }

    #[Route('/user/profile', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
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
                $errors = [];
                foreach ($violations as $v) {
                    $errors[] = ['field' => $v->getPropertyPath(), 'message' => $v->getMessage()];
                }
                return new JsonResponse(['error' => 'Validation Error', 'violations' => $errors], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['firstName'])) $user->setFirstName($data['firstName']);
        if (isset($data['lastName']))  $user->setLastName($data['lastName']);
        if (isset($data['password']))  $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));

        $this->em->flush();

        return new JsonResponse($this->serializeUser($user));
    }

    #[Route('/users/me', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        return new JsonResponse($this->serializeUser($user));
    }

    #[Route('/users/{id}', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
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
