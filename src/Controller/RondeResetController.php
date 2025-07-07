<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Ronde;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Réinitialise toutes les rondes : purge + recréation du planning.
 */
#[IsGranted('ROLE_ADMIN')]

final class RondeResetController extends AbstractController
{
    #[Route(
        '/reset/admin/rondes/reset',
        name: 'admin_rondes_reset',
        methods: ['POST'],
    )]
    public function __invoke(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        // --- CSRF ----------------------------------------------------------------
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('reset-rondes', $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        // --- purge ---------------------------------------------------------------
        $em->createQuery('DELETE FROM App\Entity\Ronde r')->execute();

        // --- nouveau planning ----------------------------------------------------
        $tz       = new \DateTimeZone('Europe/Paris');
        $planning = self::getPlanning();                            // même logique que le fixture

        foreach ($planning as $day => $sessions) {
            foreach ($sessions as [$begin, $end]) {
                $slotStart     = new \DateTimeImmutable("$day $begin", $tz);
                $slotEndLimit  = new \DateTimeImmutable("$day $end",   $tz);

                while ($slotStart < $slotEndLimit) {
                    $slotEnd = $slotStart->modify('+30 minutes');
                    if ($slotEnd > $slotEndLimit) {
                        break;
                    }

                    $em->persist(
                        (new Ronde())
                            ->setStart(\DateTime::createFromImmutable($slotStart))
                            ->setEnd(\DateTime::createFromImmutable($slotEnd))
                    );

                    $slotStart = $slotEnd;
                }
            }
        }

        $em->flush();

        $this->addFlash('success', 'Les rondes ont été réinitialisées et régénérées !');

        return $this->redirectToRoute('admin_rondes_index');
    }

    /**
     * Retourne exactement le même tableau que dans RondeFixtures.
     *
     * @return array<string, list<array{0:string,1:string}>>
     */
    private static function getPlanning(): array
    {
        return [
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
    }
}
