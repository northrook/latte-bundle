<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Runtime\Html;
use Latte\Runtime\HtmlStringable;
use Northrook\Latte\Compiler\Nodes\InlineStringableNode;
use function dump, dd, ob_get_clean, ob_start, print_r;

final class RenderExtension extends Latte\Extension
{

    public function getTags() : array {
        return [
            'inline' => [ InlineStringableNode::class, 'create' ],
        ];
    }

    public function getFilters() : array {
        return [
            'html' => static fn ( string $string ) : HtmlStringable => new Html( $string ),
        ];
    }

    public function getFunctions() : array {
        return [
            'time'        => [ $this, 'time' ],
            'debug_print' => static function ( ...$args ) : HtmlStringable {
                ob_start();
                echo '<pre>';
                foreach ( $args as $arg ) {
                    print_r( $arg );
                }
                echo '</pre>';
                return new Html( ob_get_clean() );
            },
            'debug_dump'  => static function ( ...$args ) : HtmlStringable {
                ob_start();
                foreach ( $args as $arg ) {
                    dump( $arg );
                }
                return new Html( ob_get_clean() );
            },
            'debug_dd'    => static fn ( ...$args ) => dd( $args ),
        ];
    }

    /**
     * Returns a formatted time string, based on {@see date()}.
     *
     * @param string|null  $format
     * @param int|null     $timestamp
     *
     * @return HtmlStringable
     *
     * @see https://www.php.net/manual/en/function.date.php See docs for supported formats
     */
    public function time( ?string $format = null, ?int $timestamp = null ) : HtmlStringable {

        // TODO: Add support for date and time formats
        // TODO: Add support for centralized date and time formats

        return new Html( date( $format ?? 'Y-m-d H:i:s', $timestamp ) );
    }
}