<?php

namespace Northrook\Latte;

use Latte;
use Northrook\Core\Env;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\Latte\Compiler\TemplateParser;
use Northrook\Latte\Extension\CoreExtension;
use Northrook\Latte\Extension\FormatterExtension;
use Northrook\Logger\Log;
use Northrook\Support\File;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;
use function Northrook\Core\Function\normalizeKey;
use function Northrook\Core\Function\normalizePath;

/**
 * @property-read Stopwatch $stopwatch
 * @property-read ?int      $cacheTTL
 */
final class LatteBundle
{
    use PropertyAccessor;

    private readonly Render $render;

    /** @var Latte\Extension[] */
    private array $extensions = [];

    /** @var TemplateParser[] Used by the {@see \Northrook\Latte\Loader} */
    private array $preprocessors = [];

    /** @var TemplateParser[] Applied before caching {@see renderToString}. */
    private array $postprocessors = [];

    private array $globalVariables = [];

    private array $templateDirectories = [];

    public function __construct(
        public readonly string $projectDirectory,
        public readonly string $cacheDirectory,
        string | array         $templateDirectories = [],
        array                  $extensions = [],
        array                  $globalVariables = [],
        array                  $preprocessors = [],
        array                  $postprocessors = [],
        private ?Stopwatch     $stopwatch = null,
        public ?bool           $autoRefresh = null,
    ) {
        $this->stopwatch ??= new Stopwatch( true );
        $this->setTemplateDirectories( $templateDirectories );
        $this->addExtension( ... [ new CoreExtension(), new FormatterExtension(), ... $extensions ] );
        $this->globalVariables = $globalVariables;
        $this->preprocessors   = $preprocessors;
        $this->postprocessors  = $postprocessors;
    }

    public function __get( string $property ) : int | null | Latte\Engine | Stopwatch {
        return match ( $property ) {
            'stopwatch' => $this->stopwatch,
            'cacheTTL'  => $this->cacheTTL,
        };
    }

    public function setCacheTTL( ?int $seconds ) : void {
        $this->cacheTTL = $seconds;
    }

    // Render ---------------------------------------

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
    public function render(
        string         $template,
        object | array $parameters = [],
        ?string        $block = null,
        bool           $preprocessing = true,
        bool           $postprocessing = true,
    ) : string {
        return $this->renderEngine()->renderToString( $template, $parameters, $block, $preprocessing, $postprocessing );
    }

    /**
     * Manually start the {@see Latte\Engine} at any time.
     *
     * - Properties passed to the {@see Latte\Engine} are `readonly`, including:
     * - {@see LatteBundle::templateDirectories}
     * - {@see LatteBundle::$globalVariables}
     *
     * @return void
     */
    public function startRenderEngine() : void {
        $this->renderEngine();
    }

    private function renderEngine() : Render {
        return $this->render ??= new Render(
                        $this->startEngine(),
                        $this->projectDirectory,
                        $this->templateDirectories,
                        $this->globalVariables,
            stopwatch : $this->stopwatch,
        );
    }

    // Render ----------------------------------- End
    //
    // Engine ---------------------------------------

    // public function engine( ?Latte\Loader $loader = null ) : Latte\Engine {
    //     return $this->engine ??= $this->startEngine( $loader );
    // }

    private function startEngine( ?Latte\Loader $loader = null ) : Latte\Engine {

        $this->stopwatch->start( 'latte.engine', 'Templating' );

        // Enable auto-refresh when debugging.
        if ( null === $this->autoRefresh && Env::isDebug() ) {
            Log::notice(
                'Auto-refresh enabled due to env.debug. Assign $autoRefresh manually to override this behaviour.',
            );
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

        return $latte;
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

    /**
     * Add a directory path to a `templates` directory.
     *
     * It is recommended to provide a namespace to avoid collisions.
     *
     * If no namespace is provided, generate one fromm the final directory of the path
     *
     * @param string   $path
     * @param ?string  $namespace  [optional]
     *
     * @return $this
     */
    public function addTemplateDirectory(
        string  $path,
        ?string $namespace = null,
    ) : self {
        $namespace = normalizeKey( $namespace ?? basename( $path ) );

        $this->templateDirectories[ $namespace ] = normalizePath( $path );

        return $this;
    }

    /**
     * Assign multiple directories using {@see addTemplateDirectory} internally.
     *
     * @param string|array  $templateDirectories
     *
     * @return void
     */
    public function setTemplateDirectories( string | array $templateDirectories ) : void {
        foreach ( (array) $templateDirectories as $namespace => $directory ) {
            $this->addTemplateDirectory( $directory, is_int( $namespace ) ? null : $namespace );
        }
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