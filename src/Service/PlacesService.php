<?php

namespace App\Service;

use App\Entity\Place;
use App\Entity\Seance;
use App\Repository\BilletRepository;

/**
 * User story « Réservation - Voir les places restantes ».
 * Une place est prise dès qu'elle figure dans une réservation payée, ou en attente de paiement
 * depuis moins de 15 minutes (au-delà, la réservation expire et la place est libérée).
 */
class PlacesService
{
    public function __construct(private readonly BilletRepository $billetRepository)
    {
    }

    public function placesRestantes(Seance $seance): int
    {
        return max(0, $seance->getSalle()->getCapacite() - count($this->billetRepository->idsPlacesOccupees($seance)));
    }

    /**
     * Places restantes pour une liste de séances (une seule requête).
     *
     * @param Seance[] $seances
     *
     * @return array<int, int> identifiant de séance => places restantes
     */
    public function placesRestantesPour(array $seances): array
    {
        $prises = $this->billetRepository->comptesParSeance($seances);
        $restantes = [];
        foreach ($seances as $seance) {
            $restantes[$seance->getId()] = max(0, $seance->getSalle()->getCapacite() - ($prises[$seance->getId()] ?? 0));
        }

        return $restantes;
    }

    /**
     * Plan de la salle pour une séance : chaque place avec son état (libre ou prise), rang par rang.
     *
     * @return array<string, list<array{place: Place, libre: bool}>>
     */
    public function plan(Seance $seance): array
    {
        $occupees = array_flip($this->billetRepository->idsPlacesOccupees($seance));
        $plan = [];
        foreach ($seance->getSalle()->getPlanParRang() as $rang => $places) {
            foreach ($places as $place) {
                $plan[$rang][] = ['place' => $place, 'libre' => !isset($occupees[$place->getId()])];
            }
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    public function enTableau(Seance $seance): array
    {
        $places = [];
        foreach ($this->plan($seance) as $rang => $ligne) {
            foreach ($ligne as $case) {
                $places[] = [
                    'id' => $case['place']->getId(),
                    'libelle' => $case['place']->getLibelle(),
                    'rang' => $rang,
                    'numero' => $case['place']->getNumero(),
                    'type' => $case['place']->getTypePlace(),
                    'libre' => $case['libre'],
                ];
            }
        }
        $libres = count(array_filter($places, static fn (array $p) => $p['libre']));

        return [
            'seanceId' => $seance->getId(),
            'capacite' => $seance->getSalle()->getCapacite(),
            'placesRestantes' => $libres,
            'complet' => 0 === $libres,
            'places' => $places,
        ];
    }

    /**
     * Attribution automatique au guichet : N places côte à côte, en commençant par les rangs du milieu
     * (les meilleures places). À défaut, les premières places libres.
     *
     * @return int[] identifiants des places
     */
    public function meilleuresPlacesLibres(Seance $seance, int $nombre): array
    {
        $plan = $this->plan($seance);
        $rangs = array_keys($plan);
        $milieu = (count($rangs) - 1) / 2;
        usort($rangs, static fn ($a, $b) => abs(array_search($a, array_keys($plan), true) - $milieu) <=> abs(array_search($b, array_keys($plan), true) - $milieu));

        foreach ($rangs as $rang) {
            $suite = [];
            foreach ($plan[$rang] as $case) {
                $suite = $case['libre'] ? [...$suite, $case['place']->getId()] : [];
                if (count($suite) === $nombre) {
                    return $suite;
                }
            }
        }

        $libres = [];
        foreach ($plan as $ligne) {
            foreach ($ligne as $case) {
                if ($case['libre']) {
                    $libres[] = $case['place']->getId();
                }
            }
        }

        return array_slice($libres, 0, $nombre);
    }
}
