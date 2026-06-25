<?php

namespace App\Security;

use Doctrine\DBAL\Connection;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $hash = hash('sha256', $accessToken);
        $row = $this->db->fetchAssociative('SELECT id FROM app_user WHERE api_token_hash = :h', ['h' => $hash]);
        if (false === $row) {
            throw new BadCredentialsException('Invalid API token.');
        }

        return new UserBadge($row['id'], fn () => new SecurityUser($row['id']));
    }
}
