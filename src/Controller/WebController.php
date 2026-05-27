<?php

namespace App\Controller;

use App\Repository\DemandeRepository;
use App\Repository\ServiceRepository;
use App\Repository\UserRepository;
use App\Service\AuthService;
use App\Service\DemandeService;
use App\Service\ServiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class WebController extends AbstractController
{
    public function __construct(
        private readonly AuthService        $authService,
        private readonly ServiceRepository  $serviceRepository,
        private readonly ServiceService     $serviceService,
        private readonly DemandeRepository  $demandeRepository,
        private readonly DemandeService     $demandeService,
        private readonly UserRepository     $userRepository,
    ) {}

    private function sessionUser(Request $request): ?array
    {
        return $request->getSession()->get('user');
    }

    private function guard(Request $request): ?RedirectResponse
    {
        if (!$this->sessionUser($request)) {
            return $this->redirect('/login');
        }
        return null;
    }

    private function currentUserEntity(Request $request)
    {
        $user = $this->sessionUser($request);
        return $user ? $this->userRepository->find($user['id']) : null;
    }

    // ── Home ────────────────────────────────────────────────────────────────

    #[Route('/', name: 'web_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        return $this->render('home.html.twig', [
            'user' => $this->sessionUser($request),
        ]);
    }

    // ── Login ────────────────────────────────────────────────────────────────

    #[Route('/login', name: 'web_login', methods: ['GET', 'POST'])]
    public function login(Request $request): Response
    {
        if ($this->sessionUser($request)) {
            return $this->redirect('/dashboard');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            try {
                $result  = $this->authService->login(
                    $request->request->get('email', ''),
                    $request->request->get('password', '')
                );
                $session = $request->getSession();
                $session->set('user', $result['user']);
                return $this->redirect('/dashboard');
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $this->render('login.html.twig', ['error' => $error]);
    }

    // ── Register ─────────────────────────────────────────────────────────────

    #[Route('/register', name: 'web_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->sessionUser($request)) {
            return $this->redirect('/dashboard');
        }

        $error   = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            if (!preg_match('/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/', $password)) {
                $error = 'Mot de passe invalide : min. 8 caractères, 1 majuscule, 1 minuscule, 1 chiffre.';
            } else {
                try {
                    $this->authService->register([
                        'email'     => $request->request->get('email', ''),
                        'password'  => $password,
                        'firstName' => $request->request->get('firstName', ''),
                        'lastName'  => $request->request->get('lastName', ''),
                        'roles'     => [$request->request->get('role', 'ROLE_USER')],
                    ]);
                    $success = 'Compte créé avec succès ! Vous pouvez maintenant vous connecter.';
                } catch (\InvalidArgumentException $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('register.html.twig', [
            'error'   => $error,
            'success' => $success,
        ]);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    #[Route('/dashboard', name: 'web_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): Response
    {
        if ($guard = $this->guard($request)) return $guard;

        $user     = $this->sessionUser($request);
        $services = $this->serviceRepository->findWithFilters([]);
        $demandes = $this->demandeRepository->findWithFilters(null, []);

        return $this->render('dashboard.html.twig', [
            'user'     => $user,
            'services' => array_slice($services, 0, 5),
            'demandes' => array_slice($demandes, 0, 5),
        ]);
    }

    // ── Services ──────────────────────────────────────────────────────────────

    #[Route('/services', name: 'web_services', methods: ['GET', 'POST'])]
    public function services(Request $request): Response
    {
        if ($guard = $this->guard($request)) return $guard;

        $user    = $this->sessionUser($request);
        $error   = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $roles = $user['roles'] ?? [];
            if (!in_array('ROLE_PRESTATAIRE', $roles) && !in_array('ROLE_ADMIN', $roles)) {
                $error = 'Seuls les prestataires peuvent créer des services.';
            } else {
                try {
                    $userEntity = $this->currentUserEntity($request);
                    $this->serviceService->create($userEntity, [
                        'title'       => $request->request->get('title', ''),
                        'description' => $request->request->get('description', ''),
                        'category'    => $request->request->get('category', ''),
                        'price'       => (float) $request->request->get('price', 0),
                    ]);
                    $success = 'Service créé avec succès.';
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        $services = $this->serviceRepository->findWithFilters([]);

        return $this->render('services.html.twig', [
            'user'     => $user,
            'services' => $services,
            'error'    => $error,
            'success'  => $success,
        ]);
    }

    #[Route('/services/{id}/delete', name: 'web_service_delete', methods: ['POST'])]
    public function deleteService(Request $request, string $id): Response
    {
        if ($guard = $this->guard($request)) return $guard;

        $user    = $this->sessionUser($request);
        $service = $this->serviceRepository->find($id);

        if ($service && ($service->getPrestataire()->getId() === $user['id'] || in_array('ROLE_ADMIN', $user['roles'] ?? []))) {
            $this->serviceService->delete($service);
        }

        return $this->redirect('/services');
    }

    // ── Demandes ──────────────────────────────────────────────────────────────

    #[Route('/demandes', name: 'web_demandes', methods: ['GET', 'POST'])]
    public function demandes(Request $request): Response
    {
        if ($guard = $this->guard($request)) return $guard;

        $user    = $this->sessionUser($request);
        $error   = null;
        $success = null;

        if ($request->isMethod('POST')) {
            try {
                $userEntity = $this->currentUserEntity($request);
                $this->demandeService->create($userEntity, [
                    'title'       => $request->request->get('title', ''),
                    'description' => $request->request->get('description', ''),
                    'category'    => $request->request->get('category', ''),
                    'budget'      => (float) $request->request->get('budget', 0),
                ]);
                $success = 'Demande créée avec succès.';
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $demandes = $this->demandeRepository->findWithFilters(null, []);

        return $this->render('demandes.html.twig', [
            'user'     => $user,
            'demandes' => $demandes,
            'error'    => $error,
            'success'  => $success,
        ]);
    }

    #[Route('/demandes/{id}/delete', name: 'web_demande_delete', methods: ['POST'])]
    public function deleteDemande(Request $request, string $id): Response
    {
        if ($guard = $this->guard($request)) return $guard;

        $user    = $this->sessionUser($request);
        $demande = $this->demandeRepository->find($id);

        if ($demande && ($demande->getDemandeur()->getId() === $user['id'] || in_array('ROLE_ADMIN', $user['roles'] ?? []))) {
            $this->demandeService->delete($demande);
        }

        return $this->redirect('/demandes');
    }

    // ── Logout ────────────────────────────────────────────────────────────────

    #[Route('/logout', name: 'web_logout', methods: ['GET'])]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        return $this->redirect('/');
    }
}
