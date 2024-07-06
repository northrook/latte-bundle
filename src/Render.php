<?php

namespace Northrook\Latte;

use Latte;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Minify;
use Symfony\Component\Stopwatch\Stopwatch;
use function Northrook\Core\Function\normalizePath;

final class Render
{
    use SingletonClass;

    private Latte\Engine $engine;
    private Latte\Loader $loader;


    public function __construct(
        Latte\Engine            $engine,
        private readonly string $projectDirectory, // used to check if a provided $template starts with a 'valid' path
        // private readonly string $cacheDirectory,
        private readonly array  $templateDirectories = [],
        private readonly array  $globalVariables = [],
        private readonly array  $postProcessors = [],
        private ?Stopwatch      $stopwatch = null,
    ) {
        $this->instantiationCheck();
        $this->setEngine( $engine );
        $this::$instance = $this;
    }

    public function setEngine( Latte\Engine $engine ) : void {
        $this->engine = $engine;
        $this->loader = $this->engine->getLoader();
    }

    public static function toString(
        string         $template,
        object | array $parameters = [],
        ?string        $block = null,
        bool           $preProcessing = false,
        bool           $postProcessing = true,
    ) : string{
        return Render::getInstance()->renderToString( $template, $parameters, $block, $preProcessing, $postProcessing );
    }


    /**
     * Render a given template to string.
     *
     * @param string        $template
     * @param object|array  $parameters
     * @param null|string   $block
     *
     * @param bool          $preProcessing
     * @param bool          $postProcessing
     *
     * @return string
     */
    public function renderToString(
        string         $template,
        object | array $parameters = [],
        ?string        $block = null,
        bool           $preProcessing = true,
        bool           $postProcessing = true,
    ) : string {

        $this->stopwatch->start( 'latte.render: ' . $template );

        if ( $this->loader instanceof Loader ) {
            $this->loader->parsePreprocessors = $preProcessing;
        }

        $content = $this->engine->renderToString(
            $this->load( $template ),
            $this->global( $parameters ),
            $block,
        );

        if ( $postProcessing && $this->postProcessors ) {
            foreach ( $this->postProcessors as $postProcessor ) {
                $content = $postProcessor->parseContent( $content )->toString();
            }
        }


        $html = Minify::HTML( $content );

        $this->stopwatch->stop( 'latte.render: ' . $template );

        return $html;
    }


    /**
     * @param string   $template
     * @param ?string  $namespace
     *
     * @return string
     */
    private function load( string $template, ?string $namespace = null ) : string {

        if ( !str_ends_with( $template, '.latte' ) ) {
            return $template;
        }

        $template = normalizePath( $template );

        foreach ( $this->templateDirectories as $directory ) {

            if ( str_starts_with( $template, $directory ) && file_exists( $directory ) ) {
                return $template;
            }


            $path = $directory . DIRECTORY_SEPARATOR . $template;

            if ( file_exists( $path ) ) {
                return $path;
            }
        }

        return $template;
    }

    /**
     * Adds {@see Render::$globalParameters} to all templates.
     *
     * - {@see $globalParameters} are not available when using Latte `templateType` objects.
     *
     * @param object|array  $parameters
     *
     * @return object|array
     */
    private function global( object | array $parameters ) : object | array {
        if ( is_object( $parameters ) ) {
            return $parameters;
        }

        return $this->globalVariables + $parameters;
    }
}