<?php

namespace Northrook\Latte\Compiler;

interface PostProcessorInterface
{
    /**
     * This method takes in a {@see self::$content} string,
     * parses it to match for content to process or replace,
     * then returns the modified `string`.
     *
     * @param string  $string
     *
     * @return string
     */
    public function parseContent( string $string ) : string;
}