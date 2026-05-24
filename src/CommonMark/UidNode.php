<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\CommonMark;

use Fisharebest\Webtrees\GedcomRecord;
use League\CommonMark\Node\Node;

/**
 * Inline AST node that holds a resolved GEDCOM record and optional link text.
 */
class UidNode extends Node
{
    private GedcomRecord $record;
    private string $linkText;

    public function __construct(GedcomRecord $record, string $linkText)
    {
        parent::__construct();

        $this->record   = $record;
        $this->linkText = $linkText;
    }

    public function record(): GedcomRecord
    {
        return $this->record;
    }

    public function linkText(): string
    {
        return $this->linkText;
    }
}
