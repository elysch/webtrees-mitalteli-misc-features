# Module for webtrees genealogy software. A Miscelaneous features module
===============================================================

[![Latest Release](https://img.shields.io/github/release/elysch/webtrees-mitalteli-misc-features.svg)][1]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.0.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.1.x-green)][2]
[![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)][2]
[![Downloads](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-misc-features/total.svg)]()
[![image](https://img.shields.io/github/downloads/elysch/webtrees-mitalteli-misc-features/latest/total)][1]

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif)](https://www.paypal.com/donate/?business=EU37HN97QD9EU&no_recurring=0&currency_code=MXN)

Description
A [webtrees](https://github.com/fisharebest/webtrees) module that backports several enhancements to **webtrees**, packaged as a drop-in module so no core files need to be patched.

## Features

### 1. UID/UUID references in markdown → hyperlinks

Any note or text field rendered as markdown can contain references to GEDCOM records using their `_UID` value:

```
#550e8400-e29b-41d4-a716-446655440000
#550e8400-e29b-41d4-a716-446655440000:John /Doe/
#550e8400-e29b-41d4-a716-446655440000:FULLNAME
```

- `#<UUID>` renders as a link using the record's full name.
- `#<UUID>:Custom text` renders as a link with custom text.
- `#<UUID>:FULLNAME` is an explicit alias for the record's full name.
- Backslash-escape a `#` inside the custom text: `\#`.

Supports all record types: Individual, Family, Source, Repository, Note, Media, Location.

---

### 2. Extended birth / death place search

The **Advanced Search** page gains dropdown modifiers next to the *Birth place* and *Death place* fields:

| Option | Birth behaviour | Death behaviour |
|--------|-----------------|-----------------|
| Default | Searches `BIRT:PLAC` only (existing behaviour) | Searches `DEAT:PLAC` only |
| BIRT + CHR + BAPM | Searches place across all three birth-type events | — |
| DEAT + BURI + CREM | — | Searches place across all three death-type events |

The individuals results table reflects the selected mode and shows places from all matching events.

---

### 3. COHABITATION marriage type

Adds `COHABITATION` as a recognised value for `MARR:TYPE`, displayed as *"Cohabitation"* in the UI. Also canonicalises the GEDCOM 5.5EL abbreviation `RELI` → `RELIGIOUS`.

---

### 4. NetworkService

A utility service class (`ExtendedFeatures\Services\NetworkService`) that queries WHOIS servers (radb.net, ripe.net) to retrieve the IPv4/IPv6 CIDR prefixes announced by an Autonomous System Number:

```php
$ranges = app(\ExtendedFeatures\Services\NetworkService::class)
              ->findIpRangesForAsn('AS15169');
// ['8.8.8.0/24', '8.8.4.0/24', ...]
```

---

## Installation & upgrading

1. Download the latest release ZIP.
2. Unzip into `webtrees/modules_v4/mitalteli-misc-features/`.
3. Log in to webtrees as an administrator.
4. Go to **Control panel → Modules → All modules** and enable *Misc Features*.

*NOTE: The directory name must have a maximum length of 30 characters.*

Translation
-----------
This module contains a few translatable textstrings. Copy the file es.php in the resources/lang folder and replace the Spanish text with the translation into your own language. Use the official two-letter language code as file name. Look in the webtrees folder resources/lang to find the correct code.

It would be great if you could share to the community the translated file by [creating a new issue on GitHub][3].

Bugs & feature requests
-------------------------
If you experience any bugs you can [create a new issue on GitHub][3].

## Compatibility

Tested with **webtrees 2.1.25** using PHP 7.4 and **webtrees 2.2.6** using PHP 8.3+.

## License

GNU General Public License v3.0 or later. See [LICENSE](LICENSE).


 [1]: https://github.com/elysch/webtrees-mitalteli-misc-features/releases/latest
 [2]: https://webtrees.github.io/download
 [3]: https://github.com/elysch/webtrees-mitalteli-misc-features/issues?state=open
