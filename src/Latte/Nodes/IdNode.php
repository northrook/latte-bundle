<?php

declare(strict_types=1);

namespace Northrook\Latte\Nodes;

use Latte\{CompileException, Compiler};
use Latte\Compiler\{PrintContext, Tag};
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Northrook\HTML\Element;
use Generator;

/**
 * Parsing `n:id` attributes for the {@see  Compiler\TemplateParser}.
 *
 * @copyright David Grudl
 * @see       https://davidgrudl.com  David Grudl
 * @see       https://latte.nette.org Latte Templating Engine
 *
 * @version   1.0 ✅
 * @author    Martin Nielsen <mn@northrook.com>
 *
 * @link      https://github.com/northrook Documentation
 * @todo      Update URL to documentation
 */
final class IdNode extends StatementNode
{
    public ArrayNode $args;

    /**
     * @param Tag $tag
     *
     * @return IdNode
     * @throws CompileException
     */
    public static function create( Tag $tag ) : IdNode
    {

        if ( $tag->htmlElement->getAttribute( 'id' ) ) {
            throw new CompileException( 'It is not possible to combine id with n:id.', $tag->position );
        }

        if ( ! \class_exists( Element::class ) ) {
            throw new CompileException( 'Latte tag `n:id` requires the '.Element::class.'::class to be present.' );
        }

        $node       = new IdNode();
        $node->args = $tag->parser->parseArguments();

        return $node;
    }

    public function print( PrintContext $context ) : string
    {
        return $context->format(
            'echo ($ʟ_tmp = array_filter(%node)) ? \' id="\' . '.Element::class.'::id(implode(" ", array_unique($ʟ_tmp))) . \'"\' : "" %line;',
            $this->args,
            $this->position,
        );
    }

    public function &getIterator() : Generator
    {
        yield $this->args;
    }
}
