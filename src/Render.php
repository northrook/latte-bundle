<?php

declare( strict_types = 1 );

namespace Northrook\Latte;

use Latte;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Logger\Log;
use Northrook\Minify;
use Symfony\Component\Stopwatch\Stopwatch;
use function Northrook\normalizePath;

final class Render
{
    use SingletonClass;

    private Latte\Engine $engine;
    private Latte\Loader $loader;

    /**
     * @param Latte\Engine    $engine
     * @param string          $projectDirectory  used to check if a provided $template starts with a 'valid' path
     * @param array           $templateDirectories
     * @param array           $globalVariables
     * @param array           $postprocessors
     * @param null|Stopwatch  $stopwatch
     */
    public function __construct(
        Latte\Engine                $engine,
        private readonly string     $projectDirectory,
        private readonly array      $templateDirectories = [],
        private readonly array      $globalVariables = [],
        private readonly array      $postprocessors = [],
        private readonly ?Stopwatch $stopwatch = null,
    ) {
        $this->instantiationCheck();
        $this->setEngine( $engine );
        $this::$instance = $this;
    }

    /**
     * Set the {@see Render::engine} and {@see Render::$loader} from the provided {@see Latte\Engine}.
     *
     * @param Latte\Engine  $engine
     *
     * @return void
     */
    public function setEngine( Latte\Engine $engine ) : void {
        $this->engine = $engine;
        $this->loader = $this->engine->getLoader();
    }

    /**
     * Render a given template to string.
     *
     * @param string        $template
     * @param object|array  $parameters
     * @param null|string   $block
     *
     * @param bool          $preprocessing
     * @param bool          $postprocessing
     *
     * @return string
     */
    public static function toString(
        string         $template,
        object | array $parameters = [],
        ?string        $block = null,
        bool           $preprocessing = false,
        bool           $postprocessing = true,
    ) : string {
        return Render::getInstance()->renderToString( $template, $parameters, $block, $preprocessing, $postprocessing );
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

        if ( $postProcessing && $this->postprocessors ) {
            foreach ( $this->postprocessors as $postProcessor ) {
                $content = $postProcessor->parseContent( $content )->toString();
            }
        }


        $html = Minify::HTML( $content );

        $this->stopwatch->stop( 'latte.render: ' . $template );

        return $html->__toString();
    }

    /**
     * @param string   $template
     * @param ?string  $namespace
     *
     * @return string
     */
    private function load( string $template, ?string $namespace = null ) : string {

        Log::debug( "Render::load using string: $template" );

        if ( !str_ends_with( $template, '.latte' ) ) {
            return $template;
        }

        $template = normalizePath( $template );

        if ( \str_starts_with( $template, $this->projectDirectory ) && \file_exists( $template ) ) {
            Log::debug( "Render::load was provided a full, valid template path: $template" );
            return $template;
        }

        foreach ( $this->templateDirectories as $directory ) {


            if ( \str_starts_with( $template, $directory ) && file_exists( $directory ) ) {
                return $template;
            }


            $path = $directory . DIRECTORY_SEPARATOR . $template;

            if ( \file_exists( $path ) ) {
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