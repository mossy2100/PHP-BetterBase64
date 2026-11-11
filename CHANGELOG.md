# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

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
