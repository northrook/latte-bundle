<?php

/**
 * This file is part of the Latte (https://latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

declare( strict_types = 1 );

namespace Northrook\Latte\Nodes;

use Latte\Compiler\Nodes\AreaNode;
use Latte\Compiler\Nodes\Php\ArgumentNode;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\Position;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use function Northrook\hashKey;
use function Northrook\isScalar;
use function Northrook\normalizeKey;


/**
 * {cache} ... {/cache}
 */
class CacheNode extends StatementNode
{
    public ArrayNode $args;
    public AreaNode  $content;
    public ?Position $endLine;

    public ?string $cacheId = null;

    /** @return \Generator<int, ?array, array{AreaNode, ?Tag}, static> */
    public static function create( Tag $tag ) : \Generator {
        $node       = $tag->node = new static();
        $node->args = $tag->parser->parseArguments();
        [ $node->content, $endTag ] = yield;
        $node->endLine = $endTag?->position;
        return $node;
    }


    public function parseCacheArguments( PrintContext $context ) : array {

        $arguments = $this->args->toArguments();

        $assetId     = \array_shift( $arguments ) ?? false;
        $persistence = \array_shift( $arguments ) ?? false;

        $assetId = normalizeKey(
            $assetId instanceof ArgumentNode
                ? $assetId->print( $context )
                : hashKey( $assetId ),
        );

        $persistence = $persistence instanceof ArgumentNode
            ? (int) $persistence->print( $context )
            : null;

        return [ $assetId, $persistence ];
    }

    public function print( PrintContext $context ) : string {

        [ $assetId, $persistence ] = $this->parseCacheArguments( $context );

        return $context->format(
            <<<'XX'
				$this->global->cache?->get(%dump, function( $item ) use ( $content ): string { %line
				    $item->expiresAfter( %dump? );
				    \ob_start();
					%node
					return \ob_get_flush();
				});
				%line;
				XX,
            $assetId,
            $this->position,
            $persistence,
            $this->content,
            $this->endLine,
        );
    }




// if ($this->global->cache->createCache(%dump, %node?)) %line
// try {
// %node
// $this->global->cache->end() %line;
// } catch (\Throwable $ʟ_e) {
//     $this->global->cache->rollback();
//     throw $ʟ_e;
// }

    public function &getIterator() : \Generator {
        yield $this->args;
        yield $this->content;
    }
}