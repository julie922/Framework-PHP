<?php

namespace App\Controller;

use App\Controller\Traits\ValidationTrait;
use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    use ValidationTrait;

    public function __construct(
        private readonly AuthService       $authService,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'email'     => [new Assert\NotBlank(), new Assert\Email()],
            'password'  => [new Assert\NotBlank(), new Assert\Length(min: 8), new Assert\Regex('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/')],
            'firstName' => [new Assert\NotBlank(), new Assert\Length(min: 2, max: 100)],
            'lastName'  => [new Assert\NotBlank(), new Assert\Length(min: 2, max: 100)],
            'roles'     => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\All([new Assert\Choice(choices: ['ROLE_USER', 'ROLE_PRESTATAIRE'])]),
            ]),
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        try {
            $user = $this->authService->register($data);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
            'roles'     => $user->getRoles(),
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $violations = $this->validator->validate($data, new Assert\Collection([
            'email'    => [new Assert\NotBlank(), new Assert\Email()],
            'password' => [new Assert\NotBlank()],
        ]));

        if (count($violations)) {
            return $this->validationError($violations);
        }

        try {
            $result = $this->authService->login($data['email'], $data['password']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Unauthorized', 'message' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse($result);
    }

    #[Route('/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['refreshToken'])) {
            return new JsonResponse(['error' => 'Bad Request', 'message' => 'refreshToken requis.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $this->authService->refresh($data['refreshToken']);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => 'Unauthorized', 'message' => $e->getMessage()], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'accessToken' => $result['accessToken'],
            'expiresIn'   => $result['expiresIn'],
        ]);
    }

}
