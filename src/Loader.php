<?php

declare( strict_types = 1 );

namespace Northrook\Latte;

use Latte;
use Northrook\Core\Attribute\EntryPoint;
use Northrook\Core\Attribute\ExitPoint;
use Northrook\Latte\Compiler\MissingTemplateException;
use Northrook\Latte\Compiler\TemplateParser;
use Northrook\Logger\Log;
use Northrook\Minify;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use function preg_replace;
use function str_replace;
use function trim;

final class Loader implements Latte\Loader
{
    public const LATTE_TAGS_CACHE_KEY = 'northrook-latte-loader-n-tag-cache';

    private ?string $baseDirectory = null;

    private string $content;

    public bool $parsePreprocessors = true;

    /**
     * @param TemplateParser[]  $preprocessors
     * @param array             $latteNodeTags
     */
    public function __construct(
        private readonly array $preprocessors = [],
        private readonly array $latteNodeTags = [],
    ) {}

    /**
     * Returns unique identifier for caching.
     *
     * - This is the first method called by {@see Engine::getTemplateClass()}.
     * - The {@see Engine::generateCacheHash()} method hashes the template content into a unique identifier.
     *
     */
    #[EntryPoint]
    public function getUniqueId( string $name ) : string {
        return $name;
    }

    /**
     * Returns template source code to the {@see Engine}.
     *
     * @param string  $name
     *
     * @return string
     *
     * @throws MissingTemplateException if the template cannot be found.
     */
    #[ExitPoint( [ Latte\Engine::class, 'compile' ] )]
    public function getContent( string $name ) : string {

        try {
            $this->compile( ( new Filesystem() )->readFile( $name ) );
        }
        catch ( IOException $exception ) {
            throw new MissingTemplateException(
                message  : "Unable to load template: `$name`, the template could not be read.",
                name     : $name,
                code     : 404,
                previous : $exception,
            );
        }

        return $this->content;
    }

    /**
     * @param string  $name
     * @param int     $time
     *
     * @return bool
     *
     * @deprecated Since Latte version 3.0.16
     * @link       https://github.com/nette/latte/releases/tag/v3.0.16 Deprecated since Latte version 3.0.16
     */
    public function isExpired( string $name, int $time ) : bool {
        return false;
    }

    /**
     * Returns referred template name.
     */
    public function getReferredName( string $name, string $referringName ) : string {

        if ( $this->baseDirectory || !preg_match( '#/|\\\\|[a-z][a-z0-9+.-]*:#iA', $name ) ) {
            $name = Loader::normalizePath( $referringName . '/../' . $name );
        }

        // Debug::dumpOnExit( $name, $referringName );
        return $name;
    }

    private function compile( $content ) : void {

        // Assign the $content
        $this->content = trim( $content );

        // Bail early if requested
        if ( $this->parsePreprocessors === false ) {
            return;
        }

        // Ensure elements are not broken across multiple lines.
        $this->inlineNamespacedElements()

            // Safely handle object operators
             ->protectOperators()

            // Ensure proper handling of Latte tags and their variables
             ->handleLatteTags();

        // Minify the initial template string
        //  ->compressContent();

        // Loop through each Precompiler
        foreach ( $this->preprocessors as $preprocessor ) {
            $this->content = $preprocessor->parseContent( $this->content )->toString();
        }

        // Finalize the template content for use
        $this->contentRestoreOperators()
             ->compressContent();
    }

    /**
     * Normalize a path with traversal parsing.
     *
     * @param string  $path
     *
     * @return string
     */
    protected static function normalizePath( string $path ) : string {

        $explodedPath  = explode( '/', str_replace( '\\', '/', $path ) );
        $pathDirectory = [];

        foreach ( $explodedPath as $directory ) {
            if ( $directory === '..' && $pathDirectory && end( $pathDirectory ) !== '..' ) {
                array_pop( $pathDirectory );
            }
            elseif ( $directory !== '.' ) {
                $pathDirectory[] = $directory;
            }
        }

        return implode( DIRECTORY_SEPARATOR, $pathDirectory );
    }

    /**
     * Compress the current {@see Loader::$content}.
     *
     * @return Loader
     */
    private function compressContent() : Loader {
        $this->content = (string) Minify::Latte( $this->content );

        return $this;
    }

    /**
     * # Safely handle object operators
     *
     * - Matches all `->object` operators in {@see $content}
     *
     * @return $this
     */
    private function protectOperators() : Loader {
        $this->content =
            preg_replace( pattern : '#->(?=\w)#', replacement : '%%OBJECT_OPERATOR%%', subject : $this->content );
        return $this;
    }

    /**
     * # Restore safe object operators to {@see $content}
     *
     * @return $this
     */
    private function contentRestoreOperators() : Loader {
        $this->content = str_ireplace( search : '%%OBJECT_OPERATOR%%', replace : '->', subject : $this->content );
        return $this;
    }

    /**
     * # Namespaced Elements
     *
     * - Ensures all `<custom:element  ... >` tags are on a single line.
     *
     * @return Loader
     */
    private function inlineNamespacedElements() : Loader {
        $this->content = preg_replace_callback(
            pattern  : '#<\s*[a-zA-Z][:a-zA-Z0-9]*\s+[^>]*>#',
            callback : static fn ( array $match ) => preg_replace( '/\s+/', ' ', $match[ 0 ] ?? '' ),
            subject  : $this->content,
        );

        return $this;
    }

    /**
     * # Normalize template `n:tags` and their variables.
     *
     * @return Loader
     */
    private function handleLatteTags() : Loader {

        // Match all found tags
        foreach ( $this->latteNodeTags as $tag ) {
            $this->content = preg_replace_callback(
                pattern  : "#$tag=\"(.*?)\"#s",
                callback : static function ( array $match ) use ( $tag ) {
                    // Variables in n:tags must not be bracketed, trim that and any excess whitespace
                    $value = Minify::squish( trim( $match[ 1 ], " {}" ) );
                    return $value ? "$tag=\"$value\"" : null;
                },
                subject  : $this->content,
            );
        }

        return $this;
    }
}