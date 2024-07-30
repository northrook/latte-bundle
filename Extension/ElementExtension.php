<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Northrook\Latte\Compiler\Nodes\ClassNode;
use Northrook\Latte\Compiler\Nodes\IdNode;

final class ElementExtension extends Latte\Extension
{

    public function getTags() : array {
        return [
            'n:id'    => [ IdNode::class, 'create' ],
            'n:class' => [ ClassNode::class, 'create' ],
        ];
    }
}