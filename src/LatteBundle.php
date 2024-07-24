<?php

namespace Northrook\Latte;

use Latte;
use Northrook\Core\Env;
use Northrook\Core\Timestamp;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\Filesystem\File;
use Northrook\Latte\Compiler\TemplateParser;
use Northrook\Latte\Extension\CoreExtension;
use Northrook\Latte\Extension\FormatterExtension;
use Northrook\Logger\Log;
use Northrook\Resource\Path;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\VarExporter;
use Symfony\Contracts\Cache\CacheInterface;
use function Northrook\getProjectRootDirectory;
use function Northrook\normalizeKey;
use function Northrook\normalizePath;

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

    private ?array $latteNodeTags;

    public function __construct(
        public readonly string $projectDirectory,
        public readonly string $cacheDirectory,
        // string | array          $templateDirectories = [],
        // array                   $extensions = [],
        // array                   $globalVariables = [],
        // array                   $preprocessors = [],
        // array                   $postprocessors = [],
        private ?Stopwatch     $stopwatch = null,
        public ?bool           $autoRefresh = null,
    ) {
        $this->stopwatch ??= new Stopwatch( true );
        // $this->setTemplateDirectories( $templateDirectories );
        $this->addExtension(
            ... [
                    new CoreExtension(), new FormatterExtension(),
                    // , ... $extensions
                ],
        );
        // $this->globalVariables = $globalVariables;
        // $this->preprocessors   = $preprocessors;
        // $this->postprocessors  = $postprocessors;
    }

    public function __get( string $property ) : int | null | Latte\Engine | Stopwatch {
        return match ( $property ) {
            'stopwatch' => $this->stopwatch,
        };
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
        \array_map( [ $latte, 'addExtension' ], $this->extensions );

        $loader ??= new Loader(
            preprocessors : $this->preprocessors,
            latteNodeTags : $this->latteNodeTags( $latte->getExtensions() ),
        );

        $latte->setTempDirectory( $this->cacheDirectory )
              ->setAutoRefresh( $this->autoRefresh )
              ->setLoader( $loader );

        Log::info( 'Started Latte Engine {id}.', [ 'id' => spl_object_id( $latte ), 'engine' => $latte ] );

        return $latte;
    }

    private function latteNodeTags( array $extensions ) : array {
        $count     = \count( $extensions );
        $cachePath = $this->getNodeTagsCachePath();

        if ( $cachePath->exists ) {
            $this->latteNodeTags ??= include $cachePath->path;
        }

        $useCachedExtensions =
            isset( $this->latteNodeTags[ 'extensions_count' ] ) && $this->latteNodeTags[ 'extensions_count' ] === $count;

        if ( $useCachedExtensions && isset( $this->latteNodeTags[ 'data' ] ) ) {
            return $this->latteNodeTags[ 'data' ];
        }

        // Tags not visible in Latte\Extension
        $tags = [ 'n:if' ];

        // Get all tags from all provided extensions
        foreach ( $extensions as $extension ) {
            // Append keys
            $tags += array_keys( $extension->getTags() );
        }

        // Only keep n: tags
        $tags = array_filter( $tags, static fn ( $tag ) => str_starts_with( $tag, 'n:' ) );

        // Deduplicate tags
        $tags = array_flip( array_flip( $tags ) );

        // For debugging
        Log::info( "Latte Loader: found {count} tags.", [ 'count' => count( $tags ) ] );


        $name      = 'nTag cache';
        $generated = new Timestamp();
        $generator = $this::class;


        try {
            $preparedTags = VarExporter::export(
                [
                    'name'             => $this::class . " $name",
                    'path'             => $cachePath->path,
                    'generator'        => $generator,
                    'generated'        => $generated->datetime,
                    'timestamp'        => $generated->unixTimestamp,
                    'type'             => gettype( $tags ),
                    'extensions_count' => $count,
                    'data'             => $tags,
                ],
            );
        }
        catch ( ExceptionInterface $exception ) {
            throw new \InvalidArgumentException(
                message  : "Unable to export the $name array.",
                code     : 500,
                previous : $exception,
            );
        }

        $content = <<<PHP
            <?php // $generated->unixTimestamp

            /*---------------------------------------------------------------------
            
               Name      : $name
               Generated : $generated->datetime

               This file is generated by $generator.

               Do not edit it manually.

               See https://github.com/northrook/cache for more information.

            ---------------------------------------------------------------------*/

            return $preparedTags;
            PHP;

        File::save( $cachePath->path, $content );

        dump( $count, $tags, $preparedTags );
        // Reset the index and return
        return array_values( $tags );

    }

    public function clearNodeTagsCache() : void {
        $this->getNodeTagsCachePath()->delete();
    }

    private function getNodeTagsCachePath() : Path {
        return new Path(
            sys_get_temp_dir() . '/' . hash( 'xxh3', getProjectRootDirectory() ) . '/latteNodeTagsCache.cache',
        );
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