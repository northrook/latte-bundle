<?php

declare ( strict_types = 1 );

namespace Northrook\Latte\Compiler;

use Latte\Compiler\Node;
use Latte\Compiler\NodeHelpers;
use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\FragmentNode;
use Latte\Compiler\Nodes\Html\AttributeNode;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\TextNode;
use Latte\Compiler\PrintContext;
use Northrook\HTML\Element\Attributes;
use const Northrook\WHITESPACE;

trait NodeCompilerTrait {

    final protected function isElement( Node $node, ?string $name = null ) : bool {

        if ( ! $node instanceof ElementNode ) {
            return false;
        }

        if ( $name && $node->name !== $name ) {
            return false;
        }

        return true;
    }


    final protected function resolveNodeArguments(
        PrintContext $context,
        ElementNode $node,
    ) : array {
        $attributes = [];
        $variables  = [];
        foreach ( $this->cleanNodeAttributes( $node ) as $node ) {
            $name  = $this->nodeRawValue( $node->name?->print( $context ) );
            $value = $this->nodeRawValue( $node->value?->print( $context ) );

            if ( \str_starts_with( $name, '$' ) ) {
                $variables[] = $name;
            }
            else {
                $attributes[] = "'$name' => '$value'";
            }
        }
        return [
            '[' . \implode( ', ', $attributes ) . ']',
            \implode( ', ', $variables ),
        ];
    }

    /**
     * @param ElementNode $node
     *
     * @return AttributeNode[]
     */
    final protected function cleanNodeAttributes( ElementNode $node ) : array {
        foreach ( $node->attributes->children as $index => $attribute ) {
            if ( ! $attribute instanceof AttributeNode ) {
                unset( $node->attributes->children[ $index ] );
            }
        }
        return $node->attributes->children;
    }


    final protected static function attributeNode(
        string $name,
        string | array | null $value = null,
        string $separator = WHITESPACE
    ) : AttributeNode {
        if ( \is_array( $value ) ) {
            $value = \implode( $separator, \array_filter( $value ) );
        }
        return new AttributeNode( static::text( $name ), static::text( $value ), '"' );
    }

    final protected static function text( ?string $string = null ) : ?TextNode {
        return $string !== null ? new TextNode( (string) $string ) : $string;
    }

    /**
     * @param FragmentNode|AreaNode[] $attributes
     *
     * @return AreaNode[]
     */
    final static protected function sortAttributes( FragmentNode | array $attributes ) : array {

        $children = $attributes instanceof FragmentNode ? $attributes->children : $attributes;

        foreach ( $children as $index => $attribute ) {
            unset( $children[ $index ] );
            if ( $attribute instanceof AttributeNode ) {
                $children[ NodeHelpers::toText( $attribute->name ) ] = $attribute;
            }
        }

        $attributes = [];

        foreach ( Attributes::sort( $children ) as $index => $attribute ) {
            $attributes[] = new TextNode( ' ' );
            $attributes[] = $attribute;
        }

        return $attributes;
    }


    final protected function nodeRawValue( ?string $string ) : ?string {

        if ( $string === null ) {
            return null;
        }

        if ( \str_starts_with( $string, 'echo ' ) ) {
            $string = \substr( $string, \strlen( 'echo ' ) );
        }


        if ( \str_starts_with( $string, 'LR\Filters' ) ) {
            $string = \strstr( $string, '(' );
            $string = \strchr( $string, ')', true );
        }

        return \trim( $string, " \n\r\t\v\0;()\"'" );
    }

}