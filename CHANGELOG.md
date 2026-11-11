# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Changed

- **Changed the alphabet to make encodings fully URL-safe (breaking).** The digits `{` and `}` are replaced by `_` and
  `~`, and the pad character `-` is replaced by `.`. Every character is now an unreserved URL character (RFC 3986), so
  `rawurlencode()` leaves encoded strings unchanged.
  - `_` has value 36, so `a-z` move from 36-61 to 37-62, and `~` has value 63. `0-9` and `A-Z` keep values 0-35, so
    upper-case hex and base32hex compatibility is unchanged.
  - The pad is `.` rather than `-` because `-` is a base64url digit, so a padded string passed to a base64url decoder
    by mistake fails instead of decoding to the wrong data.
  - Strings encoded with 1.0.0 won't decode. Re-encode stored data, or decode it with 1.0.0 first.

## [1.0.0] - 2026-11-11

### Added

- **`BetterBase64`,** a static class for base64 encoding that preserves sort order, using the digits `0-9`, `A-Z`,
  `a-z`, `{` and `}`, and the pad character `-`. It has 3 public methods:
  - `encode()` encodes a binary string, with optional padding.
  - `decode()` decodes an encoded string, with or without padding.
  - `isValidEncoding()` checks if a string is a valid encoding.
- **Strict decoding.** `decode()` throws a `DomainException` for invalid characters, 4n + 1 digits, the wrong amount of
  padding, or a non-canonical encoding.

### Changed

- **Renamed the package from `galaxon/base64sort` to `mossy2100/better-base64`, and the namespace from `Axon\Text` to
  `Mossy2100\BetterBase64`.** The code was converted to the Jumbo coding standard, so the methods are renamed to
  lowerCamelCase.
- **Made the digit helpers private.** `is_valid_digit_char()` is removed, and `get_digit_value()` is now private.
