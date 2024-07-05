<?php

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Runtime\Template;
use Northrook\Debug;
use Northrook\HTML\Element;
use Northrook\Latte\Nodes\ClassNode;
use Northrook\Latte\Nodes\ElementNode;
use Northrook\Latte\Nodes\IdNode;
use Psr\Log\LoggerInterface;

final class CoreExtension extends Latte\Extension
{
    public function getTags() : array {
        return [
            // 'Field'     => [ Elemåååent::class, 'create' ],
            'n:id'      => [ IdNode::class, 'create' ],
            'n:class'   => [ ClassNode::class, 'create' ],
            'n:element' => [ ElementNode::class, 'create' ],
        ];
    }

    public function getFilters() : array {
        return [
            'echo' => static function ( $string ) {
                echo $string;
            },
            'path' => [ $this, 'encodeString' ],
        ];
    }

    public function getFunctions() : array {
        return [
            'time'        => [ $this, 'time' ],
            'print_debug' => static function ( ...$args ) {
                echo '<pre>';
                foreach ( $args as $arg ) {
                    print_r( $arg );
                }
                echo '</pre>';
            },
            'var_dump'    => static function ( ...$args ) {
                var_dump( ... $args );
            },
            'dump'        => static function ( ...$args ) {
                foreach ( $args as $arg ) {
                    dump( $arg );
                }
            },
            'dd'          => static fn ( ...$args ) => dd( $args ),
        ];
    }

    /**
     * Returns a formatted time string, based on {@see date()}.
     *
     * @param string|null  $format
     * @param int|null     $timestamp
     *
     * @return string
     *
     * @see https://www.php.net/manual/en/function.date.php See docs for supported formats
     */
    public function time( ?string $format = null, ?int $timestamp = null ) : string {

        // TODO: Add support for date and time formats
        // TODO: Add support for centralized date and time formats

        return date( $format ?? 'Y-m-d H:i:s', $timestamp );
    }

    // TODO : This will be a dedicated component
    public function encodeString( string $string ) : string {
        return '<data value="' . base64_encode( $string ) . '"></data>';
    }
}