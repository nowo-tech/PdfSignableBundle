<?php

declare(strict_types=1);

namespace Nowo\PdfSignableBundle\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Default checker: the user needs at least one of `security.access_roles`. An empty list grants nothing (fail closed).
 */
final readonly class ConfigurablePdfSignableAccessChecker implements PdfSignableAccessCheckerInterface
{
    /**
     * @param list<string> $accessRoles
     */
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private array $accessRoles,
    ) {
    }

    public function canAccess(): bool
    {
        foreach ($this->accessRoles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
