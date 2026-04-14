<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use App\Entity\User;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $data['user'] = [
            'id'     => $user->getId(),
            'nom'    => $user->getLastName(),
            'prenom' => $user->getFirstName(),
            'email'  => $user->getEmail(),
            'role'   => $user->getRoles()[0],
        ];

        $event->setData($data);
    }
}
