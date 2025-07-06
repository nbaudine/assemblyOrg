<?php
// src/Twig/AppExtension.php
namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_fr', [$this, 'formatDateFr']),
        ];
    }

    public function formatDateFr(\DateTimeInterface $date): string
    {
        \Locale::setDefault('fr_FR');
        $formatter = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            $date->getTimezone()->getName(),
            \IntlDateFormatter::GREGORIAN,
            "EEEE d MMMM y"
        );

        return $formatter->format($date);
    }
}
