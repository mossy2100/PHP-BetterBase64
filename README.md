# Mossy2100 PHP BetterBase64

Base64 encoding for PHP that preserves sort order, using only characters that never need escaping in a URL.

**[License](LICENSE)** | **[Changelog](CHANGELOG.md)** | **[Documentation](docs/)**

![PHP 8.4](docs/logo_php8_4.png)

---

## Description

Base64 is everywhere, but its alphabet was never designed for ordering or URLs. Its digits run `A-Z`, `a-z`, `0-9`, `+`,
`/`, nowhere near ASCII order. So the moment you sort base64 strings, in a database index or a key-value store, the
order of the original data is lost:

```php
base64_encode("\x00");        // 'AA=='
base64_encode("\xff");        // '/w=='  sorts first, although "\xff" > "\x00"

BetterBase64::encode("\x00"); // '00'
BetterBase64::encode("\xff"); // '~l'    sorts last, as it should
```

base64url swaps `+` and `/` for `-` and `_`, which makes it URL-safe, but it has the same sorting problem.

BetterBase64 fixes both with one carefully chosen alphabet, plus `.` for padding:

```text
0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz~
```

---

## Why BetterBase64?

### Sorting works

The digits are in ascending ASCII order, so comparing two encoded strings byte by byte gives the same result as
comparing the original data.

- **Binary keys become sortable text.** Store encoded IDs, hashes or timestamps in a text column, and `ORDER BY`, range
  queries and B-tree indexes all follow the order of the underlying data.
- **Padding doesn't break it.** The pad character `.` sorts before every digit, so padded encodings stay sorted, even
  when mixed with unpadded ones.
- **Time-ordered IDs stay time-ordered.** Encode a big-endian timestamp, or a UUIDv7, and the keys sort chronologically:

```php
BetterBase64::encode(pack('J', 1700000000000));  // '0001Yx~aQ00'  2023-11-14 22:13:20.000
BetterBase64::encode(pack('J', 1700000000001));  // '0001Yx~aQ04'  1 millisecond later
BetterBase64::encode(pack('J', 1700000086400));  // '0001Yx~bjO0'  1 day later
BetterBase64::encode(pack('J', 1800000000000));  // '0001dmXSK00'  2027-01-15
```

### Compatible digits

The alphabet, in ASCII order:

| Digits | Values |
| ------ | ------ |
| `0-9`  | 0-9    |
| `A-Z`  | 10-35  |
| `_`    | 36     |
| `a-z`  | 37-62  |
| `~`    | 63     |

- Digit values match:
	- **decimal** for `0-9`
	- **upper-case hexadecimal** ([RFC 4648 §8](https://www.rfc-editor.org/rfc/rfc4648#section-8)) for `0-F`
	- **base32hex** ([RFC 4648 §7](https://www.rfc-editor.org/rfc/rfc4648#section-7)) for `0-V`

So `9` is 9, `F` is 15 and `V` is 31, exactly as you'd expect. Anyone can read a digit's value at a glance.

### URL-safe and HTML-safe

Every character BetterBase64 outputs, digits and padding alike, is an *unreserved* URL character under
[RFC 3986](https://www.rfc-editor.org/rfc/rfc3986#section-2.3). These are the characters a URL can always contain as-is,
so `rawurlencode()` leaves an encoded string unchanged. Drop one into a path segment, a query string or a fragment, and
it needs no escaping at all.

The alphabet leaves out every character that has a special meaning in a URL or in HTML:

| Context        | Characters left out                   |
| -------------- | ------------------------------------- |
| URL delimiters | `: / ? # [ ] @ ! $ & ' ( ) * + , ; =` |
| URL escapes    | `%`                                   |
| HTML           | `< > & " '`                           |

So an encoded string can go straight into HTML, with no escaping, either as text content or as an attribute value. In
text, only `<` and `&` are special, and attribute values add the quote characters. It's even valid in an *unquoted*
attribute value, which also rules out whitespace, `=` and `` ` ``, so standard base64, with its `=` padding, can't go
there. The same goes for JSON strings and most identifier formats.

The pad character `.` was chosen over `-`, the other unreserved character that sorts before the digits, because `-` is a
base64url digit. If a padded BetterBase64 string is passed to a base64url decoder by mistake, the `.` makes it fail
loudly instead of silently decoding to the wrong data.

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
| URL-safe                    | No         | Yes        | Yes          |
| Digit values match hex      | No         | No         | Yes          |
| Pad character               | `=`        | `=`        | `.`          |
| Rejects non-canonical input | Not in PHP | Not in PHP | Yes          |
| Escaped by `rawurlencode()` | `+ / =`    | `=`        | Nothing      |

### When not to use it

BetterBase64 is its own encoding, so only BetterBase64 can decode it. Use standard base64 or base64url wherever another
system expects them:

- **Data URIs.** Browsers decode `data:` URIs with standard base64 only, so an image embedded as
  `data:image/png;base64,…` must be encoded with `base64_encode()`.
- **Standard formats,** such as JWTs (base64url), and MIME email attachments and HTTP Basic authentication (base64).
- **Third-party APIs** that specify base64 or base64url.

---

## Development and Quality Assurance

This package was developed with [Claude Code](https://claude.com/product/claude-code):

- The author developed the initial concept and the encoding algorithm.
- Claude Code converted the code to the Mossy2100 coding standard, and wrote the tests and documentation.

The code is checked with:

- [PHP_CodeSniffer](https://github.com/PHPCSStandards/PHP_CodeSniffer/), for [PSR-12](https://www.php-fig.org/psr/psr-12/) compliance plus the Mossy2100 coding standard
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
BetterBase64::encode('foob');        // 'PbykOW'
BetterBase64::encode('foob', true);  // 'PbykOW..'

// Decode, with or without padding
BetterBase64::decode('PbykOW');      // 'foob'
BetterBase64::decode('PbykOW..');    // 'foob'

// Validate
BetterBase64::isValidEncoding('PbykOW');  // true
BetterBase64::isValidEncoding('PbykOX');  // false (non-canonical)

// Sort order is preserved
$encoded = array_map(BetterBase64::encode(...), ['apple', 'banana', 'cherry']);
// ['ON1lR6K', 'Ob5jOMuX', 'OrXaSc9u'], already sorted
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
