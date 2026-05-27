<?php

namespace App\Security;

use App\Entity\Demande;
use App\Entity\Proposition;
use App\Entity\Service;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ResourceVoter extends Voter
{
    public const EDIT   = 'EDIT';
    public const DELETE = 'DELETE';
    public const VIEW   = 'VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::VIEW], true)
            && ($subject instanceof Service || $subject instanceof Demande || $subject instanceof Proposition);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->hasRole('ROLE_ADMIN')) {
            return true;
        }

        return match (true) {
            $subject instanceof Service     => $this->voteOnService($attribute, $subject, $user),
            $subject instanceof Demande     => $this->voteOnDemande($attribute, $subject, $user),
            $subject instanceof Proposition => $this->voteOnProposition($attribute, $subject, $user),
            default                         => false,
        };
    }

    private function voteOnService(string $attribute, Service $service, User $user): bool
    {
        return $service->getPrestataire()->getId() === $user->getId();
    }

    private function voteOnDemande(string $attribute, Demande $demande, User $user): bool
    {
        return $demande->getDemandeur()->getId() === $user->getId();
    }

    private function voteOnProposition(string $attribute, Proposition $proposition, User $user): bool
    {
        if ($attribute === self::VIEW) {
            return $proposition->getPrestataire()->getId() === $user->getId()
                || $proposition->getDemande()->getDemandeur()->getId() === $user->getId();
        }

        if ($attribute === self::EDIT) {
            // Le demandeur peut accepter/refuser — le prestataire peut annuler sa propre proposition
            return $proposition->getDemande()->getDemandeur()->getId() === $user->getId()
                || $proposition->getPrestataire()->getId() === $user->getId();
        }

        // DELETE
        return $proposition->getPrestataire()->getId() === $user->getId();
    }
}
