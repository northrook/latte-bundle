<?php

namespace Northrook\Latte\Runtime;

use Northrook\Logger\Log;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
final  readonly class Cache
{
    public function __construct( private ?CacheInterface $cache = null ) {}

    public function get( string $assetId, callable $callback = null ) : string {
        try {
            return $this->cache->get( $assetId, $callback );
        }
        catch ( InvalidArgumentException $exception ) {
            Log::error( $exception );
            return $callback();
        }
    }
}