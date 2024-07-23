<?php

namespace Northrook\Latte\Extension;

use Latte;
use Latte\Runtime\Html;
use Northrook\HTML\Format;
use Northrook\HTML\Formatter\Newline;

final class FormatterExtension extends Latte\Extension
{
    use LatteExtensionTrait;

    public function getFilters() : array {
        return [
            'newline'      => [ $this, 'newline' ],
            'formatInline' => [ $this, 'formatInline' ],
        ];
    }

    public function newline( string $string, mixed ...$args ) : void{
        [ $condition, $attributes ] = $this->getCallableArguments( $args );
        $newline = match ( $condition ) {
            's'     => Format::newline( $string, Newline::Span, $attributes ),
            'p'     => Format::newline( $string, Newline::Paragraph, $attributes ),
            default => Format::newline( $string, Newline::Auto, $attributes ),
        };
        echo $newline;
        // return $newline;
        // dump( $condition, $attributes, $newline );
        // return new Html( $newline);
    }

    public function formatInline( string $string ) : string {
        return Format::backtickCodeTags( $string );
    }
}