<?php

namespace Northrook\Latte\Hook;

use Latte;
use Northrook\Logger\Log;

final class RenderHookExtension extends Latte\Extension
{
    public function __construct(
        private readonly RuntimeHookLoader $hookLoader,
    ) {}

    public function getFunctions() : array {
        return [
            'render'          => [ $this, 'echoRuntimeRenderHook' ],
            'getRenderString' => [ $this, 'getRuntimeRenderHook' ],
        ];
    }

    public function echoRuntimeRenderHook( string $hook, ?string $fallback = null, bool $unique = true ) : void {
        echo $this->getRuntimeRenderHook( $hook, $fallback, $unique );
    }

    public function getRuntimeRenderHook( string $hook, ?string $fallback = null, bool $unique = true ) : ?string {
        $render = $this->hookLoader->get( $hook, $unique ) ?? $fallback;
        if ( $render ) {
            Log::debug( "Rendering hook {$hook}, {$render}", [ 'hook' => $hook, 'render' => $render ] );
        }

        return $render;
    }
}