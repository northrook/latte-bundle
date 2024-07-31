<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Element;

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

final class ButtonExtension extends CompilerPassExtension
{
    use NodeCompilerTrait;

    public function traverseNodes() : array {
        return [
            [ $this, 'buttonTypeFixer' ],
        ];
    }

    public function buttonTypeFixer( Node $node ) : mixed {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'button' ) ) {

            if ( $node->getAttribute( 'type' ) ) {
                dump( $node->getAttribute( 'type' ) );
                return NodeTraverser::DontTraverseChildren;
            }

            $node->attributes->append( $this::attributeNode( 'type', 'button' ) );
            $node->attributes->children = $this::sortAttributes( $node->attributes );
        }

        return $node;
    }
}