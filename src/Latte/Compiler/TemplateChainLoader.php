<?php

declare(strict_types=1);

namespace Northrook\Latte\Compiler;

use Northrook\Exception\Trigger;
use Support\Normalize;

/**
 * @internal
 */
final class TemplateChainLoader
{
    private bool $locked = false;

    /** @var array{string: string} */
    private array $templateDirectories = [];

    public function __construct( private readonly string $projectDirectory ) {}

    public function add( string $path, bool|int $priority = false ) : void
    {
        if ( $this->locked ) {
            Trigger::valueWarning( "Template directory cannot be added, the Loader is locked.\nThe Loader is locked automatically when any template is first read." );
            return;
        }

        $priority = ( true === $priority )
                ? PHP_INT_MAX
                : $priority ?? \count( $this->templateDirectories );

        $path = Normalize::path( $path );

        if ( \in_array( $path, $this->templateDirectories ) ) {
            unset( $this->templateDirectories[\array_search( $path, $this->templateDirectories )] );
        }

        $this->templateDirectories[$priority] = $path;
    }

    /**
     * @param string $template
     *
     * @return string
     */
    public function load( string $template ) : string
    {
        if ( ! $this->locked ) {
            \krsort( $this->templateDirectories, SORT_DESC );
            $this->locked = true;
        }

        if ( ! \str_ends_with( $template, '.latte' ) ) {
            return $template;
        }

        $template = Normalize::path( $template );

        if ( \str_starts_with( $template, $this->projectDirectory ) && \file_exists( $template ) ) {
            return $template;
        }

        foreach ( $this->templateDirectories as $directory ) {
            if ( \str_starts_with( $template, $directory ) && \file_exists( $directory ) ) {
                return $template;
            }

            $path = $directory.DIRECTORY_SEPARATOR.$template;

            if ( \file_exists( $path ) ) {
                return $path;
            }
        }

        return $template;
    }
}
