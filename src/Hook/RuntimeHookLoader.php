<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Hook;

use Stringable;
use function Northrook\memoize;

final class RuntimeHookLoader
{
    /** @var array<string, string|Stringable|callable> */
    private array $hooks = [];

    /** @var array<string, int> */
    private array $rendered = [];

    /**
     * @param string  $hook
     * @param bool    $unique
     * @param bool    $returnObject
     *
     * @return null|string
     */
    public function get( string $hook, bool $unique = true, bool $returnObject = false ) : ?string {

        // Return null if the unique hook has already been used, or it doesn't exist
        if ( ( $unique && isset( $this->rendered[ $hook ] ) ) || !isset( $this->hooks[ $hook ] ) ) {
            return null;
        }

        // Note that the $hook has been rendered
        $this->rendered[ $hook ] = (    $this->rendered[ $hook ] ?? 0 ) + 1;

        if ( $returnObject ) {
            return $this->hooks[ $hook ] ?? null;
        }

        return memoize(
            static fn ( $hook ) : string => (string) ( is_callable( $hook ) ? $hook() : $hook ),
            [ $this->hooks[ $hook ] ],
        );
    }

    public function addHook( string $hook, string | Stringable | callable $string ) : void {
        $this->hooks[ $hook ] = $string;
    }

    public function getHooks() : array {
        return $this->hooks;
    }
}