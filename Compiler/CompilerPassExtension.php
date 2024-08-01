<?php

declare ( strict_types = 1 );

namespace Northrook\Latte\Compiler;

use Latte;
use Latte\Compiler\Node;
use Latte\Compiler\Nodes\Html\ElementNode;
use Latte\Compiler\Nodes\Php\ExpressionNode;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\NodeTraverser;

abstract class CompilerPassExtension extends Latte\Extension
{
    /**
     * @return callable[]
     */
    public function traverseNodes() : array {
        return [];
    }

    final public function getPasses() : array {
        return [ $this::class => [ $this, 'templateNodeTraverser' ] ];
    }

    final public function templateNodeTraverser( TemplateNode $node ) : void {
        foreach ( $this->traverseNodes() as $callback ) {
            ( new NodeTraverser() )->traverse( $node, $callback );
        }
    }
}