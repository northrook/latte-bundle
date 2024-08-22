<?php

declare ( strict_types = 1 );

namespace Northrook\Latte\Compiler;

use Latte;
use Latte\Compiler\Nodes\TemplateNode;
use Latte\Compiler\NodeTraverser;

abstract class CompilerPassExtension extends Latte\Extension
{
    /**
     * @return callable[]
     */
    abstract public function traverseNodes() : array;

    final public function getPasses() : array {
        return [ $this::class => [ $this, 'templateNodeTraverser' ] ];
    }

    final public function templateNodeTraverser( TemplateNode $node ) : void {
        foreach ( $this->traverseNodes() as $callback ) {
            ( new NodeTraverser() )->traverse( $node, $callback );
        }
    }
}