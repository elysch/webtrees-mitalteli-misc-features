# Module for webtrees genealogy software. A Miscelaneous features module
========================================================================

[![Latest Release](https://img.shields.io/github/release/elysch/webtrees-mitalteli-misc-features.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.0.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-misc-features/total.svg)]()
[![image](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-misc-features/latest/total)][1]

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?business=EU37HN97QD9EU&no_recurring=0&currency_code=MXN)

## Description

A [webtrees](https://github.com/fisharebest/webtrees) module that adds several enhancements, packaged as a drop-in module so no core files need to be patched.

## Features

### 1. UID/UUID references in markdown → hyperlinks

Any note or text field rendered as markdown can contain references to GEDCOM records using their `_UID` value:

```
#550e8400-e29b-41d4-a716-446655440000#
#550e8400-e29b-41d4-a716-446655440000:John /Doe/#
#550e8400-e29b-41d4-a716-446655440000:FULLNAME#
```

- The reference must be enclosed between `#` delimiters.
- `#<UUID>#` renders as a link using the record's full name.
- `#<UUID>:Custom text#` renders as a link with custom text.
- `#<UUID>:FULLNAME#` is an explicit alias for the record's full name.
- Backslash-escape a literal `#` inside the custom text: `\#`.

Supports all record types: Individual, Family, Source, Repository, Note, Media, Location.

> **Note on UID format variants:** GEDCOM software stores UIDs in slightly different
> formats (32, 36, or 38 hex characters, with or without dashes). The module
> normalises all variants when searching — a 36-char UID typed in a note will
> correctly link to an individual even if their `_UID` is stored as a 38-char
> Ancestry-style value. The first 32 hex characters are used for matching.

## 🛑⚠️ Critical Performance Warning (Scalability Limits)

> [!CAUTION]
> **Severe Slowdowns on Large Trees (UID / _UID Searches)**
> `webtrees` lacks database indexes on `UID` and `_UID` fields. Because of how the current database architecture is structured, adding manual indexes **will not fix** the underlying performance bottleneck.

### 📉 The Problem
When performing general searches or resolving references, the system is forced to scan every single record in the database one by one (Full Table Scan). If your tree is very large, this will result in:
* **Extreme delays** during basic search operations.
* **High CPU spikes** on your database server.
* **Frequent timeouts** and application crashes.

### ⏳ Current Status
There is **no workaround** available for this issue. This performance bottleneck is a structural limitation that will persist until a complete, fact-oriented rewrite of the `webtrees` database schema is developed and released. 


---

### 2. Improved UID / UUID search

The general and advanced search pages now find individuals and families by their
`_UID` tag value, including **38-character Ancestry-style UIDs** that the
unpatched webtrees core does not recognise.

> **Search precision note:** Searching for a UID uses the first 32 hex characters
> as the match key. Results may therefore include records whose UID shares the same
> 32-character prefix but differs in the trailing checksum bytes. In practice this
> is statistically impossible (128 bits of UUID data), but a warning is displayed
> in the search box when a UID-shaped term is detected.

**search-replace** (`/search-replace`) always uses exact matching and is not
affected by the prefix-based search.

---

### 3. Extended birth / death place search

The **Advanced Search** page gains dropdown modifiers next to the *Birth place*
and *Death place* fields:

| Option | Birth behaviour | Death behaviour |
|--------|-----------------|-----------------|
| Default | Searches `BIRT:PLAC` only | Searches `DEAT:PLAC` only |
| BIRT + CHR + BAPM | Searches place across all three birth-type events | — |
| DEAT + BURI + CREM | — | Searches place across all three death-type events |

The individuals results table reflects the selected mode and shows places from
all matching events.

---

### 4. COHABITATION marriage type

Adds `COHABITATION` as a recognised value for `MARR:TYPE`, displayed as
*"Cohabitation"* in the UI. Also canonicalises the GEDCOM 5.5EL abbreviation
`RELI` → `RELIGIOUS`.

---

### 5. NetworkService

A utility service class that queries WHOIS servers (radb.net, ripe.net) to
retrieve the IPv4/IPv6 CIDR prefixes announced by an Autonomous System Number:

```php
$ranges = app(\MitalteliMiscFeatures\Services\NetworkService::class)
              ->findIpRangesForAsn('AS15169');
// ['8.8.8.0/24', '8.8.4.0/24', ...]
```

---

## Installation & upgrading

1. Download the latest release ZIP.
2. Unzip into `webtrees/modules_v4/mitalteli-misc-features/`.
3. Log in to webtrees as an administrator.
4. Go to **Control panel → Modules → All modules** and enable *Mitalteli Misc Features*.

*NOTE: The directory name must have a maximum length of 30 characters.*

---

## Translation

This module contains a small number of translatable strings. To add a new
language, copy `resources/lang/es.php` and replace the Spanish text with your
translation. Use the official two-letter ISO 639-1 language code as the
filename (e.g. `fr.php`, `de.php`). See `webtrees/resources/lang/` for the
full list of supported codes.

If you create a translation, please share it with the community by
[opening a new issue on GitHub][3].

---

## Bugs & feature requests

If you experience any bugs or have a feature request, please
[create a new issue on GitHub][3].

---

## Compatibility

| webtrees | PHP | Status |
|----------|-----|--------|
| 2.0.x | 7.4+ | ✅ Tested |
| 2.1.x | 7.4+ | ✅ Tested |
| 2.2.x | 8.3+ | ✅ Tested |

---

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE).


 [1]: https://github.com/elysch/webtrees-mitalteli-misc-features/releases/latest
 [2]: https://webtrees.github.io/download
 [3]: https://github.com/elysch/webtrees-mitalteli-misc-features/issues?state=open
