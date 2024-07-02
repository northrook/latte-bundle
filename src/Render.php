<?php

namespace Northrook\Latte;

use Latte\Engine;
use Latte\Essential\Nodes\BlockNode;
use Northrook\Cache;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Debug;
use Northrook\Latte\Compiler\TemplateParser;
use Northrook\Logger\Log;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;

final class Render
{
    use SingletonClass;

    public function __construct(
        private readonly LatteBundle $latte,
    ) {
        $this->instantiationCheck();
        $this::$instance = $this;
    }

    private static function latte() : LatteBundle {
        return Render::getInstance()->latte;
    }

    private static function templateFile(
        string $template, object | array $parameters = [],
    ) {}


    public static function string(
        string         $template,
        object | array $parameters = [],
    ) : string {
        return Render::latte()->renderTemplate( $template, $parameters );
    }

    /**
     * Render a template to string.
     *
     * - No preprocessing.
     * - No {@see TemplateParser} parsing.
     * - Results are not cached.
     *
     * @param string        $name
     * @param object|array  $parameters
     * @param null|string   $block
     *
     * @return string
     */
    public static function template(
        string         $name,
        object | array $parameters = [],
        ?string        $block = null,
    ) : string {
        return Render::latte()->renderToString(
            template         : $name,
            parameters       : $parameters,
            block            : $block,
            postProcessing   : false,
        );
    }
}