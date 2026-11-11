<?php

declare(strict_types=1);

namespace Mossy2100\BetterBase64\Tests;

use DomainException;
use Mossy2100\BetterBase64\BetterBase64;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test class for BetterBase64.
 */
#[CoversClass(BetterBase64::class)]
final class BetterBase64Test extends TestCase
{
    #region Data providers

    /**
     * Known encodings, as [data, unpadded encoding, padded encoding].
     *
     * @return array<string, array{string, string, string}>
     */
    public static function provideKnownEncodings(): array
    {
        return [
            'empty' => ['', '', ''],
            '1 byte' => ['f', 'PW', 'PW--'],
            '2 bytes' => ['fo', 'Pcy', 'Pcy-'],
            '3 bytes' => ['foo', 'Pczl', 'Pczl'],
            '4 bytes' => ['foob', 'PczlOW', 'PczlOW--'],
            '5 bytes' => ['fooba', 'PczlOc4', 'PczlOc4-'],
            '6 bytes' => ['foobar', 'PczlOc5o', 'PczlOc5o'],
            'zero byte' => ["\x00", '00', '00--'],
            'max bytes' => ["\xff\xff\xff", '}}}}', '}}}}'],
            'sentence' => ['Hello, world!', 'I6LiR6yi87TlScna8G', 'I6LiR6yi87TlScna8G--'],
        ];
    }

    /**
     * Invalid encodings, keyed by the reason they're invalid.
     *
     * @return array<string, array{string}>
     */
    public static function provideInvalidEncodings(): array
    {
        return [
            'invalid character' => ['AB C'],
            'standard base64 character' => ['AB+/'],
            'pad character in the middle' => ['A-BC'],
            '1 digit' => ['G'],
            '4n + 1 digits' => ['ABCDE'],
            'padding only' => ['----'],
            'padding on a full quartet' => ['ABCD-'],
            'too little padding' => ['G0-'],
            'too much padding' => ['G0---'],
            'non-canonical 2-digit group' => ['G1'],
            'non-canonical 3-digit group' => ['ABD'],
            'non-canonical padded group' => ['ABD-'],
        ];
    }

    /**
     * Sets of strings to sort.
     *
     * @return array<string, array{list<string>}>
     */
    public static function provideStringsToSort(): array
    {
        return [
            'words' => [['The', 'quick', 'brown', 'fox', 'jumps', 'over', 'the', 'lazy', 'dog']],
            'prefixes' => [['G', 'GA', 'GAL', 'GALA', 'GALAX', 'GALAXO', 'GALAXON']],
            'boundary bytes' => [
                ["\x00", "\x00\x00", "\x00\xff", "\x01", "\xfe\xff\xff", "\xff", "\xff\x00", "\xff\xff"],
            ],
            'random' => [self::randomStrings(1000)],
        ];
    }

    #endregion

    #region Method encode() tests.

    /**
     * Test encode() gives the known encodings, with and without padding.
     */
    #[DataProvider('provideKnownEncodings')]
    public function testEncode(string $data, string $unpadded, string $padded): void
    {
        $this->assertSame($unpadded, BetterBase64::encode($data));
        $this->assertSame($padded, BetterBase64::encode($data, true));
    }

    /**
     * Test encode() uses only URL-, HTML- and filename-safe characters.
     */
    public function testEncodeUsesSafeCharacters(): void
    {
        $data = implode('', array_map(chr(...), range(0, 255)));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z{}]*$/', BetterBase64::encode($data));
        $this->assertMatchesRegularExpression('/^[0-9A-Za-z{}]*-*$/', BetterBase64::encode($data . 'x', true));
    }

    #endregion

    #region Method decode() tests.

    /**
     * Test decode() decodes the known encodings, with and without padding.
     */
    #[DataProvider('provideKnownEncodings')]
    public function testDecode(string $data, string $unpadded, string $padded): void
    {
        $this->assertSame($data, BetterBase64::decode($unpadded));
        $this->assertSame($data, BetterBase64::decode($padded));
    }

    /**
     * Test decode() reverses encode() for every byte value, and for random strings of every length up to 12.
     */
    public function testDecodeRoundTrip(): void
    {
        $strings = [implode('', array_map(chr(...), range(0, 255)))];
        foreach (range(0, 255) as $byte) {
            $strings[] = chr($byte);
        }
        array_push($strings, ...self::randomStrings(500));

        foreach ($strings as $string) {
            $this->assertSame($string, BetterBase64::decode(BetterBase64::encode($string)));
            $this->assertSame($string, BetterBase64::decode(BetterBase64::encode($string, true)));
        }
    }

    /**
     * Test decode() throws a DomainException for an invalid encoding.
     */
    #[DataProvider('provideInvalidEncodings')]
    public function testDecodeThrowsForInvalidEncoding(string $encoded): void
    {
        $this->expectException(DomainException::class);
        BetterBase64::decode($encoded);
    }

    #endregion

    #region Method isValidEncoding() tests.

    /**
     * Test isValidEncoding() accepts the known encodings, with and without padding.
     */
    #[DataProvider('provideKnownEncodings')]
    public function testIsValidEncodingTrue(string $data, string $unpadded, string $padded): void
    {
        $this->assertTrue(BetterBase64::isValidEncoding($unpadded));
        $this->assertTrue(BetterBase64::isValidEncoding($padded));
    }

    /**
     * Test isValidEncoding() rejects invalid encodings.
     */
    #[DataProvider('provideInvalidEncodings')]
    public function testIsValidEncodingFalse(string $encoded): void
    {
        $this->assertFalse(BetterBase64::isValidEncoding($encoded));
    }

    #endregion

    #region Sort order tests.

    /**
     * Test encoded strings sort in the same order as the original strings, with and without padding.
     *
     * @param list<string> $strings The strings to sort.
     */
    #[DataProvider('provideStringsToSort')]
    public function testEncodingPreservesSortOrder(array $strings): void
    {
        sort($strings, SORT_STRING);

        foreach ([false, true] as $includePadding) {
            $encoded = array_map(static fn(string $s) => BetterBase64::encode($s, $includePadding), $strings);
            $sorted = $encoded;
            sort($sorted, SORT_STRING);
            $this->assertSame($encoded, $sorted);
        }
    }

    /**
     * Test encoded strings sort in the same order as the original strings when padded and unpadded encodings are mixed.
     *
     * @param list<string> $strings The strings to sort.
     */
    #[DataProvider('provideStringsToSort')]
    public function testEncodingPreservesSortOrderWithMixedPadding(array $strings): void
    {
        $strings = array_values(array_unique($strings));
        sort($strings, SORT_STRING);

        // Pad every second string.
        $encoded = [];
        foreach ($strings as $i => $string) {
            $encoded[] = BetterBase64::encode($string, $i % 2 === 1);
        }
        $sorted = $encoded;
        sort($sorted, SORT_STRING);
        $this->assertSame($encoded, $sorted);
    }

    #endregion

    #region Helper methods

    /**
     * Generate random binary strings of length 0 to 12, using a fixed seed so the tests are repeatable.
     *
     * @param int $count The number of strings to generate.
     * @return list<string> The strings.
     */
    private static function randomStrings(int $count): array
    {
        mt_srand(42);
        $strings = [];
        for ($i = 0; $i < $count; $i++) {
            $string = '';
            $len = mt_rand(0, 12);
            for ($j = 0; $j < $len; $j++) {
                $string .= chr(mt_rand(0, 255));
            }
            $strings[] = $string;
        }
        mt_srand();
        return $strings;
    }

    #endregion
}
