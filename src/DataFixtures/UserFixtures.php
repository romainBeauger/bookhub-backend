<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;

class UserFixtures extends Fixture
{

    const USER_REFERENCE = 'user-1';
    const LIBRARIAN_REFERENCE = 'user-librarian';

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // 1. On crée les objets User
        $user = new User();
        $librarian = new User();
        $admin = new User();

        // 2. On remplit ses propriétés
        $user->setLastName('userFirstName');
        $user->setFirstName('userLastName');
        $user->setEmail('user@bookhub.fr');
        $user->setRoles(['ROLE_USER']);

        $librarian->setLastName('librarianFirstName');
        $librarian->setFirstName('librarianLastName');
        $librarian->setEmail('librarian@bookhub.fr');
        $librarian->setRoles(['ROLE_LIBRARIAN']);

        $admin->setLastName('adminFirstName');
        $admin->setFirstName('adminLastName');
        $admin->setEmail('admin@bookhub.fr');
        $admin->setRoles(['ROLE_ADMIN']);

        // 3. On hash le mot de passe
        $hashUser = $this->passwordHasher->hashPassword($user, 'user1234');
        $hashLibrarian = $this->passwordHasher->hashPassword($librarian, 'librarian1234');
        $hashAdmin = $this->passwordHasher->hashPassword($admin, 'admin1234');

        $user->setPassword($hashUser);
        $librarian->setPassword($hashLibrarian);
        $admin->setPassword($hashAdmin);

        // 4. On demande à Doctrine de le sauvegarder
        $manager->persist($user);
        $this->addReference(self::USER_REFERENCE, $user);
        $manager->persist($librarian);
        $this->addReference(self::LIBRARIAN_REFERENCE, $librarian);
        $manager->persist($admin);

        $manager->flush();
    }
}
