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
use Northrook\HTML\Element\Attributes;

trait NodeCompilerTrait
{

    final protected function isElement( Node $node, ?string $name = null ) : bool {

        if ( !$node instanceof ElementNode ) {
            return false;
        }

        if ( $name && $node->name !== $name ) {
            return false;
        }

        return true;
    }

    final protected static function attributeNode( string $name, ?string $value = null ) : AttributeNode {
        return new AttributeNode( static::text( $name ), static::text( $value ), '"' );
    }

    final protected static function text( ?string $string = null ) : ?TextNode {
        return $string !== null ? new TextNode( (string) $string ) : $string;
    }

    /**
     * @param FragmentNode|AreaNode[]  $attributes
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


}