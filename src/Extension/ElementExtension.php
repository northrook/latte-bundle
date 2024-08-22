<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\NodeTraverser;
use Northrook\Latte\Compiler\CompilerPassExtension;
use Northrook\Latte\Compiler\NodeCompilerTrait;
use Northrook\Latte\Nodes\ClassNode;
use Northrook\Latte\Nodes\IdNode;

final class ElementExtension extends CompilerPassExtension
{
    use NodeCompilerTrait;

    public function getTags() : array {
        return [
            'n:id'    => [ IdNode::class, 'create' ],
            'n:class' => [ ClassNode::class, 'create' ],
        ];
    }

    public function traverseNodes() : array {
        return [
            [ $this, 'buttonTypeFixer' ],
            [ $this, 'anchorReference' ],
        ];
    }

    public function anchorReference( Node $node ) : mixed {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'a' ) ) {

            // if ( $node->getAttribute( 'href' ) ) {
            // TODO : Parse tne reference
            // TODO : Look for innerContent, ensure conforms with user defaults
            // }

            // $node->attributes->append( $this::attributeNode( 'type', 'button' ) );
            $node->attributes->children = $this::sortAttributes( $node->attributes );
        }

        return $node;
    }

    public function buttonTypeFixer( Node $node ) : mixed {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'button' ) ) {

            if ( $node->getAttribute( 'type' ) ) {
                return NodeTraverser::DontTraverseChildren;
            }

            $node->attributes->append( $this::attributeNode( 'type', 'button' ) );
            $node->attributes->children = $this::sortAttributes( $node->attributes );
        }

        return $node;
    }
}