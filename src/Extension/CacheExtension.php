<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Northrook\Latte\Nodes\CacheNode;
use Northrook\Latte\Runtime\Cache;
use Symfony\Contracts\Cache\CacheInterface;


/**
 * Latte v3 extension for Nette Caching
 */
final class CacheExtension extends Latte\Extension
{
    public function __construct( private readonly ?CacheInterface $cacheInterface ) {}

    public function getTags() : array {
        return [ 'cache' => [ CacheNode::class, 'create' ] ];
    }

    /**
     * Add to the {@see CacheInterface} to the `$this->global` Latte variable.
     */
    public function getProviders() : array {
        return [ 'cache' => new Cache( $this->cacheInterface ) ];
    }
}