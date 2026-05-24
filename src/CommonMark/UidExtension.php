<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\CommonMark;

use Fisharebest\Webtrees\Tree;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

/**
 * Convert UID/UUID references within markdown text to links.
 *
 * Syntax:  #<UUID>            => link with the record's full name
 *          #<UUID>:Link Text  => link with custom text
 *          #<UUID>:FULLNAME   => link with the record's full name (explicit)
 *
 * Backslash-escape a # inside the link text: \#
 */
class UidExtension implements ExtensionInterface
{
    private Tree $tree;

    public function __construct(Tree $tree)
    {
        $this->tree = $tree;
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment
            ->addInlineParser(new UidParser($this->tree))
            ->addRenderer(UidNode::class, new UidRenderer());
    }
}
