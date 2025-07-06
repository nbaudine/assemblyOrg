<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        /* ——— Compte administrateur ——— */
        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setNom('Admin')
            ->setPrenom('Super')
            ->setRoles(['ROLE_ADMIN']);

        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'password')
        );

        $manager->persist($admin);

        /* ——— Comptes utilisateurs ——— */
        for ($i = 0; $i < 20; ++$i) {
            $user = (new User())
                ->setEmail($faker->unique()->safeEmail())
                ->setNom($faker->lastName())
                ->setPrenom($faker->firstName())
                ->setTelephone($faker->optional()->e164PhoneNumber())
                ->setRoles(['ROLE_USER']); // ROLE_USER sera ajouté automatiquement dans l’entité

            $user->setPassword(
                $this->passwordHasher->hashPassword($user, 'password')
            );

            $manager->persist($user);
        }

        $manager->flush();
    }

}
