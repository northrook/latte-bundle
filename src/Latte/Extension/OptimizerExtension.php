<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\NodeTraverser;
use const Northrook\{EMPTY_STRING, WHITESPACE};


final class OptimizerExtension extends Latte\Extension
{
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

    public function getPasses() : array
    {
        return [
            $this::class => fn( TemplateNode $templateNode ) => ( new NodeTraverser() )
                ->traverse( $templateNode, [ $this, 'traverseNodes', ] ),
        ];
    }

    public function traverseNodes() : array
    {
        $passes = [
            'nodeWhitespaceFixer' => [ $this, 'nodeWhitespaceFixer' ],
            'textWhitespaceFixer' => [ $this, 'textWhitespaceFixer' ],
        ];

        if ( $this->compress ) {
            $passes[ 'compress' ] = [ $this, 'templateCompressor' ];
        }

        return $passes;
    }

    /**
     * Fixes repeated whitespace in between element attributes.
     *
     * @param Node  $node
     *
     * @return Node
     */
    public function nodeWhitespaceFixer( Node $node ) : Node
    {
        // Bail if this isn't an ElementNode, or if the ElementNode has no attributes
        if ( !$node instanceof ElementNode || !$node->attributes->children ) {
            return $node;
        }

        // Loop though each attribute
        foreach ( $node->attributes->children as $index => $value ) {
            // Ignore anything that cannot contain a whitespace character
            if ( !$value instanceof TextNode ) {
                continue;
            }

            // Fix line breaks and surrounding whitespace
            if ( \str_contains( $value->content, "\n" ) ) {
                $value->content = match ( $index ) {
                    \array_key_last( $node->attributes->children ) => EMPTY_STRING,
                    default                                        => WHITESPACE
                };
            }
            // Fix repeated whitespace
            elseif ( \str_contains( $value->content, WHITESPACE ) ) {
                // Get the preceding node
                $previous = $node->attributes->children[ $index - 1 ] ?? null;
                if ( !$previous instanceof TextNode ) {
                    // Normalize however many spaces to one, if this node follows an attribute
                    $value->content = WHITESPACE;
                }
                else {
                    // If this node follows a text node, only add whitespace if the previous is empty
                    $value->content = $previous->content === WHITESPACE ? EMPTY_STRING : WHITESPACE;
                }
            }
        }

        return $node;
    }

    public function textWhitespaceFixer( Node $node ) : Node
    {
        if ( $node instanceof TextNode && ( $this->normalizeWhitespace || $node->isWhitespace() ) ) {
            $node->content = \preg_replace( '/(\v)+/', '$1', $node->content );
        }
        return $node;
    }

    public function templateCompressor( Node $node ) : Node
    {
        if ( $node instanceof TextNode ) {
            $node->content = \preg_replace( '/(\s)+/', WHITESPACE, $node->content );
        }
        return $node;
    }
}