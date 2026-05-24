<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures;

/**
 * GEDCOM UID constants missing from webtrees 2.1.25 core (added by the patch).
 *
 * Gedcom::REGEX_UID does not exist in the unpatched core, so we define it here.
 *
 * Supported formats:
 *   xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx  UUID v4 with dashes (36 chars)
 *   xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx       32 hex chars, no dashes
 *   xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx  38 hex chars (Ancestry-style)
 */
class GedcomUid
{
    /**
     * Regular expression to match a GEDCOM UID or _UID value.
     * Equivalent to the Gedcom::REGEX_UID constant added by the patch.
     */
    public const REGEX_UID =
        '[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{4}-?[0-9a-fA-F]{12}' .
        '|[0-9a-fA-F]{36}' .
        '|[0-9a-fA-F]{38}';
}
