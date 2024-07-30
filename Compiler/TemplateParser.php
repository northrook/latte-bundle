<?php

namespace Northrook\Latte\Compiler;

use Northrook\Core\Interface\Printable;
use Northrook\Core\Trait\PrintableClass;

/**
 * @author Martin Nielsen <mn@northrook.com>
 */
abstract class TemplateParser implements Printable
{
    use PrintableClass;

    protected string $content;

    abstract protected function parseTemplateContent() : void;

    final public function parseContent( string $string ) : self {
        $this->content = $string;
        $this->parseTemplateContent();
        return $this;
    }

    final public function __toString() : string {
        return $this->content;
    }

    final protected function updateContent( string $find, string $replace ) : void {
        $this->content = str_ireplace( $find, $replace, $this->content );
    }
}