# BetterBase64

Base64 encoding that preserves sort order, using characters that are safe in HTML and filenames.

---

## Overview

`BetterBase64` encodes binary strings in a custom base64 format, and decodes them back. It works like standard base64,
encoding each group of 3 bytes as 4 digits, but with a different alphabet:

| Digits | Values |
| ------ | ------ |
| `0-9`  | 0-9    |
| `A-Z`  | 10-35  |
| `a-z`  | 36-61  |
| `{}`   | 62-63  |

The pad character is `-`.

The digits are in ASCII order, and the pad character sorts before all of them. So comparing two encoded strings byte by
byte gives the same result as comparing the original binary strings.

`BetterBase64` is a static class, and can't be instantiated.

### Key features

- **Order-preserving** - Encoded strings sort in the same order as the original strings, with or without padding.
- **Safe characters** - No character needs escaping in HTML or a filename.
- **Not URL-safe** - `{` and `}` aren't valid URL characters, although browsers accept them. See the
  [README](../README.md#safe-in-html-and-filenames) for details, and for base64url, the URL-safe alternative.
- **Optional padding** - A final group of 1 or 2 bytes encodes as 2 or 3 digits, optionally padded to 4 characters.
- **Canonical** - Each binary string has exactly one unpadded encoding and one padded encoding. `decode()` rejects
  anything else, so equal encodings always mean equal data.

---

## Conversion Methods

### encode()

```php
public static function encode(string $data, bool $includePadding = false): string
```

Encode a binary string into a base64 string.

**Parameters:**

- `$data` (string) - The binary string to encode.
- `$includePadding` (bool) - Whether to pad the output to a multiple of 4 characters (default: `false`).

**Returns:** `string` - The encoded string.

**Examples:**

```php
BetterBase64::encode('');            // ''
BetterBase64::encode('f');           // 'PW'
BetterBase64::encode('f', true);     // 'PW--'
BetterBase64::encode('fo', true);    // 'Pcy-'
BetterBase64::encode('foo', true);   // 'Pczl'
BetterBase64::encode("\xff\xff\xff"); // '}}}}'
```

### decode()

```php
public static function decode(string $encoded): string
```

Decode a base64 string into a binary string. Padding is optional.

**Parameters:**

- `$encoded` (string) - The encoded string, with or without padding.

**Returns:** `string` - The binary string.

**Throws:**

- `DomainException` - If the string isn't a valid encoding, as described in [isValidEncoding()](#isvalidencoding).

**Examples:**

```php
BetterBase64::decode('PW');    // 'f'
BetterBase64::decode('PW--');  // 'f'
BetterBase64::decode('PW-');   // DomainException: wrong amount of padding
BetterBase64::decode('PX');    // DomainException: non-canonical
```

---

## Validation Methods

### isValidEncoding()

```php
public static function isValidEncoding(string $encoded): bool
```

Check if a string is a valid encoding, which `decode()` will accept.

A valid encoding:

- Contains only digits, optionally followed by padding.
- Doesn't have 4n + 1 digits, since 1 digit can't encode a byte.
- Has either no padding, or exactly enough to make the length a multiple of 4.
- Is canonical, i.e. the unused low bits of the last digit are zero. A final group of 2 digits has 4 unused bits, and 3
  digits has 2.

**Parameters:**

- `$encoded` (string) - The string to check.

**Returns:** `bool` - If the string is a valid encoding.

**Examples:**

```php
BetterBase64::isValidEncoding('');       // true
BetterBase64::isValidEncoding('Pcy');    // true
BetterBase64::isValidEncoding('Pcy-');   // true
BetterBase64::isValidEncoding('Pcy--');  // false: too much padding
BetterBase64::isValidEncoding('Pcz');    // false: non-canonical
BetterBase64::isValidEncoding('P');      // false: 4n + 1 digits
BetterBase64::isValidEncoding('P+/=');   // false: invalid characters
```

---

## Usage Examples

### Sortable Keys

```php
use Mossy2100\BetterBase64\BetterBase64;

$ids = ["\x00\x01", "\x00\x02", "\x10\x00", "\xff"];

// The encoded keys are already in sorted order, so they can be sorted as strings, e.g. in a database index.
$keys = array_map(BetterBase64::encode(...), $ids);
// ['004', '008', '400', '}m']
```

### Mixing Padded and Unpadded Strings

The pad character sorts after the end of a string but before every digit, so padded and unpadded encodings of different
strings still sort correctly against each other:

```php
BetterBase64::encode('f');             // 'PW'
BetterBase64::encode('f', true);       // 'PW--'
BetterBase64::encode("f\x00");         // 'PW0'
BetterBase64::encode("f\x00", true);   // 'PW0-'
// 'PW' and 'PW--' both sort before 'PW0' and 'PW0-'.
```

However, the same string has 2 different encodings. If encoded strings are compared for equality, e.g. as keys, use one
style consistently.

---

## See Also

- **[README](../README.md)** - Package overview and installation.
- **[RFC 4648](https://www.rfc-editor.org/rfc/rfc4648)** - Standard base64 and base64url encodings.
