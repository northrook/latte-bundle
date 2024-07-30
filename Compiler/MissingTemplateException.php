<?php

namespace Northrook\Latte\Compiler;

use Latte\RuntimeException;

class MissingTemplateException extends RuntimeException
{


    public function __construct(
        string                 $message,
        public readonly string $name,
        int                    $code = 0,
        ?\Throwable            $previous = null,
    ) {
        parent::__construct( $message, $code, $previous );
    }
}