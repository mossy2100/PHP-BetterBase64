<?php

declare(strict_types=1);

namespace Mossy2100\BetterBase64;

use Mossy2100\Core\Exceptions\FormatException;

/**
 * Encodes binary strings into a custom base64 format, and decodes them back into binary.
 *
 * The unique feature of this encoding is that encoded strings sort in the same order as the original strings, with or
 * without padding.
 *
 * The digit characters and the pad character are also safe to use in URLs, HTML and filenames.
 */
final class BetterBase64
{
    #region Constants

    /**
     * The digits, in ascending order of both value and byte value, so encoded strings sort like the originals.
     */
    private const string DIGITS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz{}';

    /**
     * The pad character. It sorts before every digit, so padded encodings keep the sort order too.
     */
    private const string PAD_CHAR = '-';

    #endregion

    #region Constructor

    /**
     * Private constructor to prevent instantiation.
     *
     * @codeCoverageIgnore
     */
    private function __construct() {}

    #endregion

    #region Conversion methods

    /**
     * Encode a binary string into a base64 string.
     *
     * Each group of 3 bytes is encoded as 4 digits. A final group of 1 or 2 bytes is encoded as 2 or 3 digits, and
     * optionally padded to 4 characters with the pad character.
     *
     * @param string $data The binary string to encode.
     * @param bool $includePadding Whether to pad the output to a multiple of 4 characters.
     * @return string The base64 encoded string.
     */
    public static function encode(string $data, bool $includePadding = false): string
    {
        // How many bytes to encode.
        $len = strlen($data);

        // Accumulate the result.
        $encoded = '';

        // Keep track of the position within a triplet.
        $pos = 0;

        // Accumulate the int value of the triplet.
        $int = 0;

        // Loop through the input bytes, converting triplets of bytes to quartets of digits.
        for ($i = 0; $i < $len; $i++) {
            // Add the next byte.
            $int = $int * 256 + ord($data[$i]);

            // Advance the position within the triplet.
            $pos++;

            // See if we're at the end of a triplet (or a shorter sequence of 1-2 bytes at the end).
            if ($pos === 3 || $i === $len - 1) {
                // Make sure the number of bits collected is a multiple of 6.
                if ($pos === 1) {
                    // 1 byte is 8 bits, so shift left by 4 bits to make 12 bits, which is 2 digits.
                    $int <<= 4;
                } elseif ($pos === 2) {
                    // 2 bytes is 16 bits, so shift left by 2 bits to make 18 bits, which is 3 digits.
                    $int <<= 2;
                }

                // Accumulate the digits in the quartet.
                $digits = '';

                // Convert the int to a sequence of up to 4 characters.
                for ($j = 0; $j < 4; $j++) {
                    if ($j <= $pos) {
                        // Prepend the digit for the lowest 6 bits, then shift them off.
                        $digits = self::DIGITS[$int % 64] . $digits;
                        $int >>= 6;
                    } elseif ($includePadding) {
                        $digits .= self::PAD_CHAR;
                    }
                }

                // Append the new characters to the encoded string.
                $encoded .= $digits;

                // Start the next triplet.
                $pos = 0;
                $int = 0;
            }
        }

        return $encoded;
    }

    /**
     * Decode a base64 string into a binary string.
     *
     * The string must be a valid encoding, as checked by isValidEncoding().
     *
     * @param string $encoded The base64 encoded string, with or without padding.
     * @return string The binary string.
     * @throws FormatException If the string isn't a valid encoding.
     */
    public static function decode(string $encoded): string
    {
        // Check the string.
        $error = self::findError($encoded);
        if ($error !== null) {
            throw new FormatException($error);
        }

        // Trim the padding.
        $digitString = rtrim($encoded, self::PAD_CHAR);

        // How many digits to decode.
        $len = strlen($digitString);

        // Accumulate the result.
        $data = '';

        // Keep track of the position within a quartet.
        $pos = 0;

        // Accumulate the int value of the quartet.
        $int = 0;

        // Loop through the digits, converting quartets of digits to triplets of bytes.
        for ($i = 0; $i < $len; $i++) {
            // Add the next digit.
            $int = $int * 64 + self::getDigitValue($digitString[$i]);

            // Advance the position within the quartet.
            $pos++;

            // See if we're at the end of a quartet (or a shorter sequence of 2-3 digits at the end).
            if ($pos === 4 || $i === $len - 1) {
                // Drop the unused bits.
                if ($pos === 2) {
                    // 2 digits is 12 bits, so drop 4 bits to get 1 byte.
                    $int >>= 4;
                } elseif ($pos === 3) {
                    // 3 digits is 18 bits, so drop 2 bits to get 2 bytes.
                    $int >>= 2;
                }

                // Accumulate the bytes in the triplet.
                $bytes = '';

                // Convert the int to a sequence of up to 3 bytes.
                for ($j = 0; $j < $pos - 1; $j++) {
                    // Prepend the byte for the lowest 8 bits, then shift them off.
                    $bytes = chr($int % 256) . $bytes;
                    $int >>= 8;
                }

                // Append the new bytes to the result.
                $data .= $bytes;

                // Start the next quartet.
                $pos = 0;
                $int = 0;
            }
        }

        return $data;
    }

    #endregion

    #region Validation methods

    /**
     * Check if a string is a valid encoding, which decode() will accept.
     *
     * A valid encoding:
     * - Contains only digits, optionally followed by padding.
     * - Doesn't have 4n + 1 digits, since 1 digit can't encode a byte.
     * - Has either no padding, or exactly enough to make the length a multiple of 4.
     * - Is canonical, i.e. the unused low bits of the last digit are zero.
     *
     * @param string $encoded The string to check.
     * @return bool If the string is a valid encoding.
     */
    public static function isValidEncoding(string $encoded): bool
    {
        return self::findError($encoded) === null;
    }

    #endregion

    #region Helper methods

    /**
     * Find the reason a string isn't a valid encoding, if any.
     *
     * @param string $encoded The string to check.
     * @return ?string The error message, or null if the string is a valid encoding.
     */
    private static function findError(string $encoded): ?string
    {
        // Separate the digits from the padding.
        $digitString = rtrim($encoded, self::PAD_CHAR);
        $nDigits = strlen($digitString);
        $nPads = strlen($encoded) - $nDigits;

        // Check the characters.
        if (strspn($digitString, self::DIGITS) !== $nDigits) {
            return 'Encoded strings can only contain digits, letters and braces, optionally followed by padding.';
        }

        // Check the number of digits. 2 digits encode 1 byte, 3 digits encode 2 bytes, and 4 digits encode 3 bytes, so
        // a final group of 1 digit is impossible.
        $remainder = $nDigits % 4;
        if ($remainder === 1) {
            return 'Encoded strings cannot have 4n + 1 digits.';
        }

        // Check the padding.
        if ($nPads > 0 && ($remainder === 0 || $remainder + $nPads !== 4)) {
            return 'Encoded strings must have no padding, or enough to make the length a multiple of 4.';
        }

        // Check the encoding is canonical. A final group of 2 digits has 4 unused bits, and 3 digits has 2.
        if ($remainder > 0) {
            $unusedBitMask = $remainder === 2 ? 0b1111 : 0b11;
            if ((self::getDigitValue($digitString[$nDigits - 1]) & $unusedBitMask) !== 0) {
                return 'Encoded strings must be canonical, with the unused bits of the last digit set to zero.';
            }
        }

        return null;
    }

    /**
     * Get the value of a digit.
     *
     * @param string $char The digit, which must be valid.
     * @return int The value of the digit, in the range [0, 63].
     */
    private static function getDigitValue(string $char): int
    {
        return (int) strpos(self::DIGITS, $char);
    }

    #endregion
}
