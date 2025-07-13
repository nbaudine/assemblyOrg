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

        $usersData = [
            ['ALIX', 'Philippe', '06 52 97 36 12', 'philalix70@gmail.com', ''],
            ['BARROS', 'Sami', '06 23 26 50 38', 'samais33@live.fr', 'AM'],
            ['BAUDINE', 'Nathan', '06 51 93 29 06', 'baudinenathan@gmail.com', 'AM'],
            ['BERNARD', 'Franck', '06 32 53 45 06', 'franckbernard68@live.fr', 'AM'],
            ['BROCHARD', 'Logan', '06 17 39 56 14', 'logan.marine@outlook.fr', ''],
            ['BUCHET', 'Hugo', '06 18 48 44 14', 'hb2894@gmail.com', '-'],
            ['BURIEL', 'Erwann', '07 81 38 27 09', 'burrial.erwann@gmail.com', ''],
            ['BUDZINSKI', 'Ruben', '07 68 00 06 33', 'rbnbudzinski@gmail.com', 'AM'],
            ['BUDZINSKI', 'Joël', '06 61 05 90 88', 'joel.budzinski@free.fr', 'Ancien'],
            ['BUDZINSKI', 'Mathis', '07 69 17 28 82', 'budzinskimathis@gmail.com', 'AM'],
            ['CAPOCHICHI', 'Gabin', '06 21 58 16 25', 'capochichigabin@yahoo.fr', 'AM'],
            ['CAUGNON', 'Jérémie', '07 68 99 88 58', 'caugnonjeremie@gmail.com', ''],
            ['CORNU', 'Ruben', '07 77 72 51 26', 'rubencornu1@gmail.com', ''],
            ['CORNU', 'Amandine', '06 70 19 22 38', 'amandinemariondelmas@gmail.com', ''],
            ['COUSIN', 'David', '', 'cousin.800vfr@gmail.com', ''],
            ['CHAUMET', 'Serge', '06 99 10 58 71', 'Sermicho@gmail.com', '-'],
            ['CHAUMET', 'Fabien', '06 60 27 46 35', 'fabienchaumet@yahoo.fr', 'AM'],
            ['DAMACENO', 'Georges', '06 99 23 42 72', 'jorgedamaceno16@gmail.com', 'AM'],
            ['DAMACENO', 'Florian', '06 84 40 63 03', 'damaceno.florian@gmail.com', '-'],
            ['FERRAND', 'Steeve', '07 82 79 98 62', 'steeve.ferrand03@gmail.com', 'Ancien'],
            ['FILIMONOV', 'Andreï', '07 52 44 21 04', 'filimonovap@gmail.com', '-'],
            ['GASHET', 'Alexy', '07 62 64 30 71', 'alexydu17@hotmail.fr', ''],
            ['GRATTEPANCHE', 'Steeve', '06 22 92 34 53', 'sgrattepanche@hotmail.fr', 'AM'],
            ['GUILLEMANT', 'Alain', '06 26 61 50 30', 'alain.guillement@sfr.fr', ''],
            ['HERRONEAU', 'Nathanael', '06 18 47 30 70', 'heronneaunathanael@gmail.co', ''],
            ['LEBRAULT', 'Laurent', '06 61 38 93 91', 'lebrault2@aol.com', 'AM'],
            ['MACE', 'Marc', '06 62 65 40 14', 'marco56mace@gmail.com', '-'],
            ['MORAND', 'Mikaël', '06 75 69 11 65', 'morandmik@gmail.com', 'Ancien'],
            ['MOREIRA', 'Bruno', '07 82 44 35 90', 'bruno.moreira17@laposte.net', '-'],
            ['NOGAJ', 'Tiziana', '07 82 27 74 35', 'tiziana.nogaj@gmail.com', '-'],
            ['PEREIRA', 'Patrick', '06 23 88 60 92', 'patrickmag8@hotmail.com', '-'],
            ['PIERRE', 'Quentin', '07 64 20 59 87', 'quentin.16.qp@gmail.com', 'Ancien'],
            ['PHILIPPE', 'Corentin', '06 88 25 85 44', 'corentinphilippe@hotmail.com', '-'],
            ['PHILIPPE', 'Néhémie', '07 88 99 67 11', 'damacenonehemie0510@outlook.com', '-'],
            ['PHILIPPE', 'Grégoire', '06 42 08 54 67', 'gregphilippe8@gmail.com', ''],
            ['RIMBERT', 'Alexandra', '07 50 91 97 88', 'alexandra.ragon@yahoo.fr', '-'],
            ['SOULIER', 'Nolan', '06 64 17 62 34', 'soulier.nolhan@gmail.com', 'AM'],
            ['STANG', 'Julien', '07 68 88 38 35', 'julien.stang@hotmail.fr', ''],
            ['TRANCHANT', 'Léo', '07 84 03 86 37', 'leo.tranchant1@gmail.com', ''],
            ['UGARTEMANDIA', 'Sébastien', '06 87 09 60 39', 'seb.ugart@gmail.com', '-'],
        ];

        foreach ($usersData as [$nom, $prenom, $tel, $email, $statut]) {
            $user = new User();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setTelephone($tel);
            $user->setEmail($email);

            // Définir les rôles
            if (
                ($nom === 'BAUDINE' && $prenom === 'Nathan') ||
                ($nom === 'BUDZINSKI' && in_array($prenom, ['Ruben', 'Mathis', 'Joël']))
            ) {
                $user->setRoles(['ROLE_ADMIN']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }



            $hashedPassword = $this->passwordHasher->hashPassword($user, '144000');
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }


        $manager->flush();
    }

}
