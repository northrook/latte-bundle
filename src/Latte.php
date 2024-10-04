<?php

declare(strict_types=1);

namespace Northrook;

use Closure;
use Latte\{Engine, Extension, Loader as LoaderInterface};
use Latte\Loaders\FileLoader;
use LogicException;
use Northrook\Filesystem\File;
use Northrook\Latte\Compiler\TemplateChainLoader;
use Northrook\Logger\Log;
use Psr\Log\LoggerInterface;
use Throwable;
use TypeError;

/**
 * @author Martin Nielsen <mn@northrook.com>
 */
class Latte
{
    private static Latte $environment;

    protected LoaderInterface|Closure $loader;

    private readonly TemplateChainLoader $templateLoader;

    private Engine $engine;

    private array $globalVariables = [];

    /** @var Extension[] */
    private array $extensions = [];

    /** @var callable[] */
    private array $postprocessors = [];

    public function __construct(
        protected string                    $projectDirectory,
        protected string                    $cacheDirectory,
        protected string                    $locale = 'en',
        protected readonly ?LoggerInterface $logger = null,
        public bool                         $autoRefresh = true,
    ) {
        $this->templateLoader = new TemplateChainLoader( $this->projectDirectory );
        $this->setStaticAccessor();
    }

    public static function render(
        string       $template,
        object|array $parameters = [],
        ?string      $block = null,
        bool         $postProcessing = true,
    ) : string {
        return static::$environment?->templateToString( ...\get_defined_vars() )
               // TODO : Provide link to Documentation
               ?? throw new LogicException( 'The '.static::class.' has not been initialized yet.' );
    }

    final public function templateToString(
        string       $template,
        object|array $parameters = [],
        ?string      $block = null,
        bool         $postProcessing = true,
    ) : string {
        $content = $this->engine()->renderToString(
            $this->templateLoader->load( $template ),
            $this->global( $parameters ),
            $block,
        );

        if ( $postProcessing ) {
            $content = $this->postProcessing( $content );
        }

        return $content;
    }

    final protected function postProcessing( string $string ) : string
    {
        foreach ( $this->postprocessors as $postprocessor ) {
            $string = (string) $postprocessor( $string );
        }

        return $string;
    }

    final protected function engine() : Engine
    {
        return $this->engine ??= $this->startEngine();
    }

    private function startEngine() : Engine
    {
        if ( ! \file_exists( $this->cacheDirectory ) ) {
            File::mkdir( $this->cacheDirectory );
        }

        // Initialize the Engine.
        $this->engine = new Engine();

        // Add all registered extensions to the Engine.
        \array_map( [$this->engine, 'addExtension'], $this->extensions );

        $this->engine
            ->setTempDirectory( $this->cacheDirectory )
            ->setAutoRefresh( $this->autoRefresh )
            ->setLoader( $this->loader() )
            ->setLocale( $this->locale );

        $this->logger?->info(
            'Started Latte Engine {id}.',
            [
                'id'     => \spl_object_id( $this->engine ),
                'engine' => $this->engine,
            ],
        );

        return $this->engine;
    }

    final protected function loader() : ?LoaderInterface
    {
        if ( ! isset( $this->loader ) ) {
            return $this->loader = new FileLoader();
        }

        if ( $this->loader instanceof Closure ) {
            try {
                $this->loader = $this->loader->__invoke();
            }
            catch ( Throwable $exception ) {
                throw new TypeError( message: $this::class.' could not use provided Loader. The passed Closure is not a valid '.LoaderInterface::class, previous : $exception );
            }
        }

        return $this->loader;
    }

    final public function setLoader( LoaderInterface|Closure $loader ) : self
    {
        $this->loader = $loader;
        return $this;
    }

    public function addGlobalVariable( string $key, mixed $value ) : self
    {
        $this->globalVariables[$key] = $value;

        return $this;
    }

    /**
     * Add {@see Extension}s.
     *
     * @param Extension ...$extension
     *
     * @return $this
     */
    final public function addExtension( Extension ...$extension ) : static
    {
        foreach ( $extension as $addExtension ) {
            if ( \in_array( $addExtension, $this->extensions, true ) ) {
                Log::warning(
                    '{caller} tried to add an already existing extension. Please ensure your config files; you likely have a duplicate call somewhere.',
                    ['caller' => $this::class.'->addExtension'],
                );

                continue;
            }
            $this->extensions[] = $addExtension;
        }

        return $this;
    }

    public function addPostprocessor( Closure|callable ...$templateParser ) : self
    {
        foreach ( $templateParser as $templatePostprocessor ) {
            $this->postprocessors[] = $templatePostprocessor;
        }
        return $this;
    }

    /**
     * Add a directory path to a `templates` directory.
     *
     * - You can set a template priority, higher means it will be checked earlier in the chain.
     * - Setting priority:true sets the highest possible priority
     *
     * @param string   $path
     * @param bool|int $priority
     *
     * @return $this
     */
    final public function addTemplateDirectory( string $path, bool|int $priority = false ) : static
    {
        $this->templateLoader->add( $path, $priority );
        return $this;
    }

    final public function pruneTemplateCache() : void
    {
        $templates = [];

        foreach ( \glob( $this->cacheDirectory.'/*.php' ) as $file ) {
            $templates[\basename( $file )] = $file;
        }

        Log::info(
            'Pruned {count} templates from cache.',
            [
                'count'  => \count( $templates ),
                'pruned' => $templates,
            ],
        );
    }

    final public function clearTemplateCache() : bool
    {
        return File::remove( $this->cacheDirectory );
    }

    /**
     * Adds {@see Latte::$globalVariables} to all templates.
     *
     * - {@see $globalVariables} are not available when using Latte `templateType` objects.
     *
     * @param array|object $parameters
     *
     * @return array|object
     */
    private function global( object|array $parameters ) : object|array
    {
        if ( \is_object( $parameters ) ) {
            return $parameters;
        }

        return $this->globalVariables + $parameters;
    }

    private function setStaticAccessor() : void
    {
        if ( isset( static::$environment ) ) {
            throw new LogicException( 'The Latte environment is a Singleton, and cannot be instantiated twice.' );
        }
        $this::$environment ??= $this;
    }
}
