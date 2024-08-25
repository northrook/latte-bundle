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
use const Northrook\EMPTY_STRING;

final class ElementExtension extends CompilerPassExtension {

    use NodeCompilerTrait;

    public function getTags() : array {
        return [
            'n:id'    => [ IdNode::class, 'create' ],
            'n:class' => [ ClassNode::class, 'create' ],
        ];
    }

    public function traverseNodes() : array {
        return [
            [ $this, 'headingSystem' ],
            [ $this, 'imgAltAttribute' ],
            [ $this, 'buttonTypeFixer' ],
            // [ $this, 'anchorReference' ],
            [ $this, 'sortNodeAttributes' ],
        ];
    }

    public function headingSystem( Node $node ) : int | Node {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( ! $node instanceof ElementNode ) {
            return $node;
        }

        if ( $this->isHeading( $node->name ) ) {
            $node->attributes->append( $this::attributeNode(
                'class', [ 'heading', $node->getAttribute( 'class' ), ]
            ) );

        }

        if ( $node->name === 'small' && $this->isHeading( $node->parent->name ) ) {
            $node->attributes->append( $this::attributeNode(
                'class', [ 'subheading', $node->getAttribute( 'class' ), ]
            ) );
        }

        $node->attributes->children = $this::sortAttributes( $node->attributes );
        return $node;
    }

    public function imgAltAttribute( Node $node ) : int | Node {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'img' ) ) {

            if ( ! $node->getAttribute( 'alt' ) ) {
                $node->attributes->append( $this::attributeNode( 'alt', EMPTY_STRING ) );
            }
        }

        return $node;
    }

    public function anchorReference( Node $node ) : int | Node {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'a' ) ) {

            // if ( $node->getAttribute( 'href' ) ) {
            // TODO : Parse tne reference
            // TODO : Look for innerContent, ensure conforms with user defaults
            // }

            // $node->attributes->append( $this::attributeNode( 'type', 'button' ) );
        }

        return $node;
    }

    public function buttonTypeFixer( Node $node ) : int | Node {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode && $node->is( 'button' ) ) {

            if ( $node->getAttribute( 'type' ) ) {
                $node->attributes->append( $this::attributeNode( 'type', 'button' ) );
            }
        }

        return $node;
    }


    public function sortNodeAttributes( Node $node ) : int | Node {

        if ( $node instanceof ExpressionNode ) {
            return NodeTraverser::DontTraverseChildren;
        }

        if ( $node instanceof ElementNode ) {
            $node->attributes->children = $this::sortAttributes( $node->attributes );
        }

        return $node;
    }


    private function isHeading( string $string ) : bool {
        return \in_array( $string, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true );
    }
}