# Mossy2100 PHP BetterBase64

Base64 encoding for PHP that preserves sort order, using characters that are safe in HTML and filenames.

**[License](LICENSE)** | **[Changelog](CHANGELOG.md)** | **[Documentation](docs/)**

![PHP 8.4](docs/logo_php8_4.png)

---

## Description

Base64 is everywhere, but its alphabet was never designed for ordering or filenames. Its digits run `A-Z`, `a-z`, `0-9`,
`+`, `/`, nowhere near ASCII order. So the moment you sort base64 strings, in a database index, a file listing or a
key-value store, the order of the original data is lost:

```php
base64_encode("\x00");        // 'AA=='
base64_encode("\xff");        // '/w=='  sorts first, although "\xff" > "\x00"

BetterBase64::encode("\x00"); // '00'
BetterBase64::encode("\xff"); // '}m'    sorts last, as it should
```

base64url swaps `+` and `/` for `-` and `_`, but has the same problem.

BetterBase64 fixes this with one carefully chosen alphabet:

```text
0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz{}
```

---

## Why BetterBase64?

### Sorting just works

The digits are in ascending ASCII order, so comparing two encoded strings byte by byte gives the same result as
comparing the original data. This is the defining feature of the design.

- **Binary keys become sortable text.** Store encoded IDs, hashes or timestamps in a text column, and `ORDER BY`, range
  queries and B-tree indexes all follow the order of the underlying data.
- **Padding doesn't break it.** The pad character `-` sorts before every digit, so padded encodings stay sorted, even
  when mixed with unpadded ones.
- **Time-ordered IDs stay time-ordered.** Encode a big-endian timestamp, or a UUIDv7, and the keys sort chronologically:

```php
BetterBase64::encode(pack('J', 1700000000000));  // '0001Yy}bQ00'  2023-11-14 22:13:20.000
BetterBase64::encode(pack('J', 1700000000001));  // '0001Yy}bQ04'  1 millisecond later
BetterBase64::encode(pack('J', 1700000086400));  // '0001Yy}ckO0'  1 day later
BetterBase64::encode(pack('J', 1800000000000));  // '0001enXSK00'  2027-01-15
```

### Digits you already know

The alphabet is four groups, each in its natural order: the 10 decimal digits, the 26 upper-case letters, the 26
lower-case letters, then `{` and `}`.

- Digit values match **decimal** for `0-9`, **upper-case hexadecimal** for `0-F`, and **base32hex** ([RFC 4648](https://www.rfc-editor.org/rfc/rfc4648#section-7))
  for `0-V`.
- So `5` is 5, `F` is 15 and `V` is 31, exactly as you'd expect. Anyone can read a digit's value at a glance.

### Safe in HTML and filenames

The alphabet deliberately leaves out the characters that commonly need escaping or cause confusion:

| Context         | Characters left out                 |
| --------------- | ----------------------------------- |
| HTML            | `< > & " ' =`                       |
| URL delimiters  | `@ / ? & = # % + [ ] ! $ ( ) * , ;` |
| Number notation | `. , _`                             |

Encoded strings can go into HTML attributes, JSON, filenames and identifiers without a second layer of escaping, and `-`
is a safer pad character than base64's `=`, which is a delimiter in URL query strings.

> [!IMPORTANT]
> **BetterBase64 isn't URL-safe.** RFC 3986 doesn't allow `{` and `}` in URLs, so `rawurlencode()` escapes them as `%7B`
> and `%7D`.
>
> In practice, browsers accept `{` and `}` in URLs, so a link containing an encoded string works without escaping.
> However, a browser may percent-encode them in the path before sending the request, so decode the URL path (most
> frameworks do this already) before decoding the string.
>
> If you need URL-safe strings and don't need sort order, use base64url
> ([RFC 4648 §5](https://www.rfc-editor.org/rfc/rfc4648#section-5)) instead:
>
> ```php
> $encoded = rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
> $decoded = base64_decode(strtr($encoded, '-_', '+/'));
> ```

### Canonical and strict

Each binary string has exactly one unpadded encoding and one padded encoding. `decode()` rejects anything else: stray
characters, impossible lengths, wrong padding, and non-zero unused bits. So two encoded strings in the same style are
equal exactly when their data is equal.

### No size penalty

It's still base64: 3 bytes encode as 4 characters. A 16-byte UUID becomes 22 characters, compared with 32 in hex or 36
in the standard UUID format.

### At a glance

| Feature                     | base64     | base64url  | BetterBase64 |
| --------------------------- | ---------- | ---------- | ------------ |
| Sorts like the data         | No         | No         | Yes          |
| URL-safe                    | No         | Yes        | No           |
| Filename-safe               | No         | Yes        | Yes          |
| Digit values match hex      | No         | No         | Yes          |
| Pad character               | `=`        | `=`        | `-`          |
| Rejects non-canonical input | Not in PHP | Not in PHP | Yes          |
| Escaped by `rawurlencode()` | `+ / =`    | `=`        | `{ }`        |

---

## Development and Quality Assurance

This package was developed with [Claude Code](https://claude.com/product/claude-code):

- The author developed the initial concept and the encoding algorithm.
- Claude Code converted the code to the Mossy2100 coding standard, and wrote the tests and documentation.

The code is checked with:

- [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/), for [PSR-12](https://www.php-fig.org/psr/psr-12/)
  compliance plus the Mossy2100 coding standard
- [PHPStan](https://phpstan.org/), at level 9
- [PHPUnit](https://phpunit.de/index.html), with 100% code coverage

![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen)

---

## Requirements

- PHP ^8.4

No other dependencies.

---

## Installation

```bash
composer require mossy2100/better-base64
```

---

## Quick Start

```php
use Mossy2100\BetterBase64\BetterBase64;

// Encode, with or without padding
BetterBase64::encode('foob');        // 'PczlOW'
BetterBase64::encode('foob', true);  // 'PczlOW--'

// Decode, with or without padding
BetterBase64::decode('PczlOW');      // 'foob'
BetterBase64::decode('PczlOW--');    // 'foob'

// Validate
BetterBase64::isValidEncoding('PczlOW');  // true
BetterBase64::isValidEncoding('PczlOX');  // false (non-canonical)

// Sort order is preserved
$encoded = array_map(BetterBase64::encode(...), ['apple', 'banana', 'cherry']);
// ['ON1mR6K', 'Oc5kOMvX', 'OsXbSd9v'], already sorted
```

---

## Classes

### [BetterBase64](docs/BetterBase64.md)

Static class with methods to encode binary strings, decode them, and check whether a string is a valid encoding.

---

## Testing

The library includes comprehensive test coverage:

```bash
# Run all tests
vendor/bin/phpunit

# Run with coverage (generates HTML report and clover.xml)
composer test
```

---

## License

MIT License - see [LICENSE](LICENSE) for details

---

## Support

- **Issues**: https://github.com/mossy2100/PHP-BetterBase64/issues
- **Documentation**: See [docs/](docs/) directory for detailed class documentation
- **Examples**: See test files for comprehensive usage examples

For questions or suggestions, please [open an issue](https://github.com/mossy2100/PHP-BetterBase64/issues).

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history and changes.
