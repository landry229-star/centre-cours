<?php

namespace Tests\Unit;

use App\Support\AmountInWords;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function numbers(): array
    {
        return [
            'zéro' => [0, 'zéro'],
            'seize' => [16, 'seize'],
            'vingt et un' => [21, 'vingt et un'],
            'vingt-deux' => [22, 'vingt-deux'],
            'soixante-dix' => [70, 'soixante-dix'],
            'soixante et onze' => [71, 'soixante et onze'],
            'soixante-dix-sept' => [77, 'soixante-dix-sept'],
            'quatre-vingts' => [80, 'quatre-vingts'],
            'quatre-vingt-un' => [81, 'quatre-vingt-un'],
            'quatre-vingt-onze' => [91, 'quatre-vingt-onze'],
            'quatre-vingt-dix-neuf' => [99, 'quatre-vingt-dix-neuf'],
            'cent' => [100, 'cent'],
            'cent un' => [101, 'cent un'],
            'cent quatre-vingts' => [180, 'cent quatre-vingts'],
            'deux cents' => [200, 'deux cents'],
            'deux cent un' => [201, 'deux cent un'],
            'mille' => [1000, 'mille'],
            'mille un' => [1001, 'mille un'],
            'deux mille' => [2000, 'deux mille'],
            'quinze mille' => [15000, 'quinze mille'],
            'quatre-vingt mille (pas de s devant mille)' => [80000, 'quatre-vingt mille'],
            'deux cent mille (pas de s devant mille)' => [200000, 'deux cent mille'],
            'deux cent quatre-vingt mille' => [280000, 'deux cent quatre-vingt mille'],
            'un million' => [1000000, 'un million'],
            'deux millions' => [2000000, 'deux millions'],
            'deux millions deux cent mille' => [2200000, 'deux millions deux cent mille'],
            'deux cents millions (s devant million)' => [200000000, 'deux cents millions'],
            'grand nombre' => [1234567, 'un million deux cent trente-quatre mille cinq cent soixante-sept'],
            'un milliard' => [1000000000, 'un milliard'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_spells_numbers_in_french(int $number, string $expected): void
    {
        $this->assertSame($expected, AmountInWords::number($number));
    }

    public function test_francs_has_capital_letter_and_correct_plural(): void
    {
        $this->assertSame('Quinze mille francs CFA', AmountInWords::francs(15000));
        $this->assertSame('Un franc CFA', AmountInWords::francs(1));
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        AmountInWords::number(-5);
    }
}
