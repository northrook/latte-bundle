<?php

declare( strict_types = 1 );

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Runtime\Html;
use Latte\Runtime\HtmlStringable;
use Northrook\HTML\Format;
use Northrook\HTML\Formatter\Newline;


final class FormatterExtension extends Latte\Extension
{
    public function getFilters() : array
    {
        return [
            'newline'      => [ $this, 'newline' ],
            'formatInline' => [ $this, 'formatInline' ],
        ];
    }

    public function newline( string $string, mixed ...$args ) : HtmlStringable
    {
        [ $condition, $attributes ] = $this->getCallableArguments( $args );
        $newline = match ( $condition ) {
            's'     => Format::newline( $string, Newline::Span, $attributes ),
            'p'     => Format::newline( $string, Newline::Paragraph, $attributes ),
            default => Format::newline( $string, Newline::Auto, $attributes ),
        };
        return new Html( $newline );
    }

    public function formatInline( string $string ) : string
    {
        return Format::backtickCodeTags( $string );
    }

    /**
     * @param mixed  $arguments
     *
     * @return array{condition: ?string, arguments: []}
     */
    final protected function getCallableArguments( mixed $arguments ) : array
    {
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