<?php

namespace App\Tests\Unit;

use App\Service\PaiementService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaiementServiceTest extends TestCase
{
    #[DataProvider('cartes')]
    public function testLaCleDeLuhnDetecteLesFautesDeFrappe(string $numero, bool $valide): void
    {
        self::assertSame($valide, PaiementService::numeroCarteValide($numero));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function cartes(): iterable
    {
        yield 'carte de test acceptée' => ['4242424242424242', true];
        yield 'carte de test refusée (numéro valide)' => ['4000000000000002', true];
        yield 'un chiffre faux' => ['4242424242424241', false];
        yield 'trop court' => ['4242', false];
        yield 'lettres' => ['4242abcd42424242', false];
    }

    public function testLaDateDExpirationEstControlee(): void
    {
        $maintenant = new \DateTimeImmutable('2026-11-20');

        self::assertTrue(PaiementService::expirationValide('11/26', $maintenant), 'valable jusqu\'à la fin du mois');
        self::assertTrue(PaiementService::expirationValide('03/29', $maintenant));
        self::assertFalse(PaiementService::expirationValide('10/26', $maintenant));
        self::assertFalse(PaiementService::expirationValide('13/27', $maintenant));
        self::assertFalse(PaiementService::expirationValide('1127', $maintenant));
    }
}
