<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\CommonMark;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Stringable;

/**
 * Render a UidNode as an <a href="...">...</a> element.
 *
 * Link text priority:
 *  1. If empty or "FULLNAME" → record full name
 *  2. Otherwise → the custom text supplied after the colon
 */
class UidRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
    {
        UidNode::assertInstanceOf($node);

        /** @var UidNode $node */
        $href = $node->record()->url();

        $text = $node->linkText();

        if ($text === '' || $text === 'FULLNAME') {
            $html = $node->record()->fullName();
        } else {
            $html = e($text);
        }

        return new HtmlElement('a', ['href' => $href], $html);
    }
}
