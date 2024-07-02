<?php

namespace Northrook\Latte;

use Latte;
use Northrook\Cache;
use Northrook\Core\Env;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\Latte\Compiler\TemplateParser;
use Northrook\Logger\Log;
use Northrook\Latte\Extension\CoreExtension;
use Northrook\Minify;
use Northrook\Support\File;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;
use function Northrook\Core\Function\normalizeKey;
use function Northrook\Core\Function\normalizePath;
use function Northrook\Core\Function\replaceEach;

/**
 * @property-read Stopwatch $stopwatch
 * @property-read ?int      $cacheTTL
 */
final class LatteBundle
{
    use PropertyAccessor;

    /** @var Latte\Engine[] */
    private array $engine = [];

    /** @var Latte\Extension[] */
    private array $extensions = [];

    /** @var TemplateParser[] Used by the {@see \Northrook\Latte\Loader} */
    private array $preprocessors = [];

    /** @var TemplateParser[] Applied before caching {@see renderToString}. */
    private array $postprocessors = [];

    private array $globalVariables     = [];
    private array $templateDirectories = [];

    public function __construct(
        public readonly string $cacheDirectory,
        string | array         $templateDirectories = [],
        array                  $globalVariables = [],
        array                  $extensions = [],
        array                  $preprocessors = [],
        array                  $postprocessors = [],
        private ?Stopwatch     $stopwatch = null,
        public ?bool           $autoRefresh = null,
        private ?int           $cacheTTL = 1,
    ) {
        $this->stopwatch ??= new Stopwatch( true );
        $this->setTemplateDirectories( $templateDirectories );
        $this->addExtension( ... [ new CoreExtension(), ... $extensions ] );
        $this->preprocessors   = $preprocessors;
        $this->postprocessors  = $postprocessors;
        $this->globalVariables = $globalVariables;
    }

    public function __get( string $property ) : int | null | Latte\Engine | Stopwatch {
        return match ( $property ) {
            'engine'    => $this->engine(),
            'stopwatch' => $this->stopwatch,
            'cacheTTL'  => $this->cacheTTL,
        };
    }

    public function setCacheTTL( ?int $seconds ) : void {
        $this->cacheTTL = $seconds;
    }

    // Render ---------------------------------------

    public function renderTemplate(
        string         $template,
        object | array $parameters = [],
        ?string        $block = null,
        ?int           $persistence = Cache::EPHEMERAL,
    ) : string {
        return Cache::memoize(
            callback    : [ $this, 'renderToString' ],
            arguments   : [ $template, $parameters, $block ],
            persistence : $persistence ?? $this->cacheTTL,
        );
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

        $engine = $this->engine();
        $loader = $engine->getLoader();

        if ( $loader instanceof Loader ) {
            $loader->parsePreprocessors = $preProcessing;
        }

        $content = $engine->renderToString(
            $this->load( $template ),
            $this->global( $parameters ),
            $block,
        );

        if ( $postProcessing && $this->postprocessors ) {
            foreach ( $this->postprocessors as $postprocessor ) {
                $content = $postprocessor->parseContent( $content )->toString();
            }
        }

        return Minify::HTML( $content );
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

        $template    = normalizePath( $template );
        $directories = $this->getTemplateDirectories( $namespace );

        foreach ( $directories as $directory ) {

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

    // Render ----------------------------------- End
    //
    // Engine ---------------------------------------

    public function engine( ?Latte\Loader $loader = null ) : Latte\Engine {
        return $this->engine[ $loader ?? Loader::class ] ??= $this->startEngine( $loader );
    }

    private function startEngine( ?Latte\Loader $loader = null ) : Latte\Engine {

        // Enable auto-refresh when debugging.
        if ( null === $this->autoRefresh && Env::isDebug() ) {
            Log::notice( 'Auto-refresh enabled due to env.debug' );
            $this->autoRefresh = true;
        }

        File::mkdir( $this->cacheDirectory );

        // Initialize the Engine.
        $latte = new Latte\Engine();

        // Add all registered extensions to the Engine.
        array_map( [ $latte, 'addExtension' ], $this->extensions );

        $loader ??= new Loader(
            extensions    : $latte->getExtensions(),
            preprocessors : $this->preprocessors,
        );

        $latte->setTempDirectory( $this->cacheDirectory )
              ->setAutoRefresh( $this->autoRefresh )
              ->setLoader( $loader );

        Log::info( 'Started Latte Engine {id}.', [ 'id' => spl_object_id( $latte ), 'engine' => $latte ] );

        dump( $latte );

        return $latte;
    }

    public function stopEngine() : self {
        unset( $this->engine );
        return $this;
    }

    /**
     * Add {@see Latte\Extension}s to this {@see Environment}.
     *
     * @param Latte\Extension  ...$extension
     *
     * @return $this
     */
    public function addExtension( Latte\Extension ...$extension ) : LatteBundle {

        foreach ( $extension as $ext ) {
            if ( in_array( $ext, $this->extensions, true ) ) {
                continue;
            }
            $this->extensions[] = $ext;
        }

        return $this;
    }

    // Engine ----------------------------------- End
    //
    // Variables ------------------------------------

    public function getGlobalVariables() : array {
        return $this->globalVariables;
    }

    public function addGlobalVariable( string $key, mixed $value ) : self {
        $this->globalVariables[ $key ] = $value;

        return $this;
    }

    // Variables -------------------------------- End
    //
    // Template Parsers -----------------------------

    public function addPreprocessor( TemplateParser ...$templateParser ) : self {
        foreach ( $templateParser as $templatePreprocessor ) {
            $this->preprocessors[ $templatePreprocessor::class ] = $templatePreprocessor;
        }
        return $this;
    }

    public function addPostprocessor( TemplateParser ...$templateParser ) : self {
        foreach ( $templateParser as $templatePostprocessor ) {
            $this->preprocessors[ $templatePostprocessor::class ] = $templatePostprocessor;
        }
        return $this;
    }

    // Template Parsers ------------------------- End
    //
    // Templates ------------------------------------

    public function addTemplateDirectory( string $directory, $namespace = null ) : self {
        if ( is_string( $namespace ) ) {
            $this->templateDirectories[] = [
                'namespace' => normalizeKey( $namespace ),
                'path'      => normalizePath( $directory ),
            ];
        }
        else {
            $this->templateDirectories[] = normalizePath( $directory );
        }

        return $this;
    }

    public function setTemplateDirectories( string | array $templateDirectories ) : void {
        foreach ( (array) $templateDirectories as $namespace => $directory ) {
            $this->addTemplateDirectory( $directory, $namespace );
        }
    }

    public function getTemplateDirectories( ?string $namespace = null ) : array {
        $directories = [];
        foreach ( $this->templateDirectories as $directory ) {
            if ( !$namespace ) {
                $directories[] = is_string( $directory ) ? $directory : $directory[ 'path' ];
                continue;
            }

            if ( is_array( $directory ) && $namespace === $directory[ 'namespace' ] ) {
                $directories[] = $directory[ 'path' ];
            }

        }
        return $directories;
    }

    public function clearCache() : bool {
        try {
            ( new Filesystem() )->remove( $this->cacheDirectory );
        }
        catch ( IOException $e ) {
            Log::error( $e->getMessage() );
            return false;
        }

        return true;
    }

    // Templates -------------------------------- End
}