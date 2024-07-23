<?php

namespace Northrook\Latte\Extension;

trait LatteExtensionTrait
{

    /**
     * @param mixed  $arguments
     *
     * @return array{condition: ?string, arguments: []}
     */
    final protected function getCallableArguments( mixed $arguments ) : array {


        // If the $arguments are empty, return null condition and empty arguments
        if ( !$arguments ) {
            return [ null, [] ];
        }

        if ( \count( $arguments ) > 1 ) {
            throw new \BadMethodCallException( "The arguments array should contain exactly one argument." );
        }

        if ( \array_is_list( $arguments ) ) {
            return [ $arguments[ 0 ], [] ];
        }

        $condition = \array_key_first( $arguments );
        $arguments = \reset( $arguments );

        if ( !\is_array( $arguments ) ) {
            throw new \BadMethodCallException( "The arguments array should contain an array." );
        }

        return [
            $condition,
            $arguments,
        ];
    }
}