<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\Elements;

use Fisharebest\Webtrees\Elements\AbstractElement;
use Fisharebest\Webtrees\I18N;

use function strtoupper;

/**
 * Extended MARR:TYPE element.
 *
 * Adds COHABITATION to the standard marriage types and canonicalises the
 * GEDCOM 5.5EL abbreviation RELI → RELIGIOUS.
 */
class MarriageType extends AbstractElement
{
    public const VALUE_CIVIL        = 'CIVIL';
    public const VALUE_COMMON_LAW   = 'COMMON LAW';
    public const VALUE_COHABITATION = 'COHABITATION';
    public const VALUE_PARTNERS     = 'PARTNERS';
    public const VALUE_RELIGIOUS    = 'RELIGIOUS';

    /**
     * Convert a value to a canonical form.
     * GEDCOM 5.5EL uses 'RELI' for 'Religious marriage' (RELIGIOUS).
     */
    public function canonical(string $value): string
    {
        $value = strtoupper(parent::canonical($value));

        $canonical = [
            'RELI' => self::VALUE_RELIGIOUS,
        ];

        return $canonical[$value] ?? $value;
    }

    /**
     * A list of controlled values for this element.
     *
     * @return array<int|string,string>
     */
    public function values(): array
    {
        return [
            ''                       => '',
            self::VALUE_CIVIL        => I18N::translate('Civil marriage'),
            self::VALUE_COMMON_LAW   => I18N::translate('Common-law marriage'),
            self::VALUE_COHABITATION => I18N::translate('Cohabitation'),
            self::VALUE_PARTNERS     => I18N::translate('Registered partnership'),
            self::VALUE_RELIGIOUS    => I18N::translate('Religious marriage'),
        ];
    }
}
