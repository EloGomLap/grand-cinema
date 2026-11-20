<?php

namespace App\Exception;

/**
 * Une ou plusieurs places demandées viennent d'être réservées par quelqu'un d'autre (code HTTP 409).
 */
class PlaceDejaPriseException extends \RuntimeException
{
    /**
     * @param string[] $places libellés des places déjà prises (ex. C7)
     */
    public function __construct(private readonly array $places = [])
    {
        parent::__construct([] === $places
            ? 'Une des places vient d\'être réservée, merci d\'en choisir une autre.'
            : sprintf('Place%s déjà réservée%s : %s. Merci d\'en choisir d\'autres.', count($places) > 1 ? 's' : '', count($places) > 1 ? 's' : '', implode(', ', $places)));
    }

    /**
     * @return string[]
     */
    public function getPlaces(): array
    {
        return $this->places;
    }
}
