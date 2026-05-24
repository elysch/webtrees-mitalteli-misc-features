<?php

/**
 * mitalteli-misc-features
 * Copyright (C) 2025
 */

declare(strict_types=1);

namespace MitalteliMiscFeatures\Factories;

use MitalteliMiscFeatures\CommonMark\UidExtension;
use Fisharebest\Webtrees\CommonMark\CensusTableExtension;
use Fisharebest\Webtrees\CommonMark\XrefExtension;
use Fisharebest\Webtrees\Contracts\MarkdownFactoryInterface;
use Fisharebest\Webtrees\Tree;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\NewlineParser;
use League\CommonMark\Renderer\Block\DocumentRenderer;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\Inline\NewlineRenderer;
use League\CommonMark\Renderer\Inline\TextRenderer;
use League\CommonMark\Util\HtmlFilter;

use function strip_tags;
use function strtr;

/**
 * Drop-in replacement for the core MarkdownFactory that additionally registers
 * the UidExtension so that #UUID references in notes/markdown become clickable
 * links to the corresponding GEDCOM records.
 */
class MarkdownFactory implements MarkdownFactoryInterface
{
    // Keep in sync with core MarkdownFactory::BREAK
    public const BREAK = '<br />';

    protected const CONFIG_AUTOLINK = [
        'allow_unsafe_links' => false,
        'html_input'         => HtmlFilter::ESCAPE,
        'renderer'           => [
            'soft_break' => self::BREAK,
        ],
    ];

    protected const CONFIG_MARKDOWN = [
        'allow_unsafe_links' => false,
        'html_input'         => HtmlFilter::ESCAPE,
        'renderer'           => [
            'soft_break' => self::BREAK,
        ],
        'table'              => [
            'wrap' => [
                'enabled'    => true,
                'tag'        => 'div',
                'attributes' => [
                    'class' => 'table-responsive',
                ],
            ],
        ],
    ];

    /**
     * Minimal CommonMark processor with auto-link + UID support.
     */
    public function autolink(string $markdown, ?Tree $tree = null): string
    {
        $environment = new Environment(static::CONFIG_AUTOLINK);
        $environment->addInlineParser(new NewlineParser());
        $environment->addRenderer(Document::class, new DocumentRenderer());
        $environment->addRenderer(Paragraph::class, new ParagraphRenderer());
        $environment->addRenderer(Text::class, new TextRenderer());
        $environment->addRenderer(Link::class, new LinkRenderer());
        $environment->addRenderer(Newline::class, new NewlineRenderer());
        $environment->addExtension(new AutolinkExtension());

        if ($tree instanceof Tree) {
            $environment->addExtension(new XrefExtension($tree));
            $environment->addExtension(new UidExtension($tree));
        }

        $converter = new MarkdownConverter($environment);
        $html      = $converter->convert($markdown)->getContent();
        $html      = strip_tags($html, ['a', 'br', 'p']);

        return strtr($html, ["\n" => '']);
    }

    /**
     * Full CommonMark processor with table + census + UID support.
     */
    public function markdown(string $markdown, ?Tree $tree = null): string
    {
        $environment = new Environment(static::CONFIG_MARKDOWN);
        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new CensusTableExtension());

        if ($tree instanceof Tree) {
            $environment->addExtension(new XrefExtension($tree));
            $environment->addExtension(new UidExtension($tree));
        }

        $converter = new MarkdownConverter($environment);
        $html      = $converter->convert($markdown)->getContent();

        return strtr($html, ["\n" => '']);
    }
}
