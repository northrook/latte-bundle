<?php

use Northrook\Latte\Compiler\TemplateParser;

class ComponentParser extends TemplateParser
{

    protected function parseTemplateContent() : void {
        $count = preg_match_all(
        /** @lang PhpRegExp */
            pattern : '/<(?<component>(\w*?):.*?)>/ms',
            subject : $this->content,
            matches : $fields,
            flags   : PREG_SET_ORDER,
        );

        \Northrook\Debug::dumpOnExit( $count, $fields );
    }
}