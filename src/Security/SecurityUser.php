<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;


final class SecurityUser implements UserInterface
{
    /**
     * @param non-empty-string $id
     * @param list<non-empty-string> $roles
     */
    public function __construct(
        private readonly string $id,
        private readonly array $roles = ['ROLE_USER'],
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}
