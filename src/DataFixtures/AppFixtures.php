<?php

namespace App\DataFixtures;

use App\Entity\Author;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {

        // Création d'un user
        $user = new User;
        $user->setEmail("user@biblioapi.fr");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "motdepasse"));
        $manager->persist($user);

        // Création d'un admin

        $admin = new User;
        $admin->setEmail("admin@biblioapi.fr");
        $admin->setRoles(["ROLE_ADMIN"]);
        $admin->setPassword($this->userPasswordHasher->hashPassword($admin, "motdepasse2"));
        $manager->persist($admin);


        // Création des auteurs

        $listAuthor = [];
        for($i=20; $i < 30; $i++){
            $author = new Author();
            $author->setFirstName('Prénom ' . $i);
            $author->setLastName('Nom ' . $i);
            $manager->persist($author);
            $listAuthor[] = $author; 
        }

        // Création des livres

        for($i=50; $i < 70; $i++){
            $livre = new Book();
            $livre->setTitle('Livre ' . $i);
            $livre->setCoverText('Quatrième de couverture du livre n°' . $i);
            $livre->setAuthor($listAuthor[array_rand($listAuthor)]);
            $manager->persist($livre);
        }

        $manager->flush();
    }
}
