<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\NodeTraverser;
use Northrook\HTML\Element\Attributes;
use Northrook\Latte\Compiler\CompilerPassExtension;
use Northrook\Latte\Compiler\NodeCompilerTrait;

final class OptimizerExtension extends CompilerPassExtension
{
    use NodeCompilerTrait;

    /**
     * - Fixes repeated vertical white spaces outside of Nodes
     *
     * @param bool  $normalizeWhitespace  Fixes repeated vertical spaces everywhere
     * @param bool  $compress             Squishes the entire template, smallest possible result
     */
    public function __construct(
        public readonly bool $normalizeWhitespace = false,
        public readonly bool $compress = false,
    ) {}

    public function traverseNodes() : array {
        $passes = [
            'whitespaceFixer' => [ $this, 'whitespaceFixer' ],
        ];

        if ( $this->compress ) {
            $passes[ 'compress' ] = [ $this, 'templateCompressor' ];
        }

        return $passes;
    }

    public function whitespaceFixer( Node $node ) : mixed {
        if ( $node instanceof TextNode && ( $this->normalizeWhitespace || $node->isWhitespace() ) ) {
            $node->content = \preg_replace( '/(\v)+/', '$1', $node->content );
        }
        return $node;
    }

    public function templateCompressor( Node $node ) : mixed {
        if ( $node instanceof TextNode ) {
            $node->content = \preg_replace( '/(\s)+/', ' ', $node->content );
        }
        return $node;
    }
}