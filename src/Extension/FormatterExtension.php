<?php

namespace Northrook\Latte\Extension;

use Latte;
use Northrook\ContentFormatter\Format;

final class FormatterExtension extends Latte\Extension
{
    public function getFilters() : array {
        return [
            'nl2auto'      => static fn ( $string ) => Format::nl2Auto( $string ),
            'nl2span'      => static fn ( $string ) => Format::nl2span( $string ),
            'nl2p'         => static fn ( $string ) => Format::nl2p( $string ),
            'formatInline' => [ $this, 'formatInline' ],
        ];
    }

    public function formatInline( string $string ) : string {
        return Format::backtickCodeTags( $string );
    }
}