<?php
declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Ronde;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

final class RondeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $tz = new \DateTimeZone('Europe/Paris');

        /** @var array<string, array<array{0:string,1:string}>> $planning */
        $planning = [
            // Vendredi 18 juillet 2025
            '2025-07-18' => [
                ['09:30', '12:00'],
                ['13:30', '16:30'],
            ],
            // Samedi 19 juillet 2025
            '2025-07-19' => [
                ['09:30', '12:00'],
                ['13:00', '16:30'],
            ],
            // Dimanche 20 juillet 2025
            '2025-07-20' => [
                ['09:30', '12:00'],
                ['13:00', '15:30'],
            ],
        ];

        foreach ($planning as $day => $sessions) {
            foreach ($sessions as [$begin, $end]) {
                $slotStart = new \DateTimeImmutable("$day $begin", $tz);
                $slotEndLimit = new \DateTimeImmutable("$day $end", $tz);

                while ($slotStart < $slotEndLimit) {
                    $slotEnd = $slotStart->modify('+30 minutes');

                    // On s’arrête exactement à la limite définie
                    if ($slotEnd > $slotEndLimit) {
                        break;
                    }

                    $ronde = (new Ronde())
                        ->setStart(\DateTime::createFromImmutable($slotStart))
                        ->setEnd(\DateTime::createFromImmutable($slotEnd));

                    $manager->persist($ronde);

                    $slotStart = $slotEnd;
                }
            }
        }

        $manager->flush();
    }
}
