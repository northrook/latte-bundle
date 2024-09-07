<?php

declare ( strict_types = 1 );

namespace Northrook\Latte\Nodes;

use Latte\CompileException;
use Latte\Compiler;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Northrook\HTML\Element;


/**
 * Parsing `n:class` attributes for the {@see  Compiler\TemplateParser}
 *
 * @see       https://davidgrudl.com  David Grudl
 * @see       https://latte.nette.org Latte Templating Engine
 *
 * @todo      Update URL to documentation
 * @version   1.0 ✅
 * @author    Martin Nielsen <mn@northrook.com>
 *
 * @link      https://github.com/northrook Documentation
 * @copyright David Grudl
 */
final class ClassNode extends StatementNode
{
    public ArrayNode $args;

    /**
     * @throws CompileException
     */
    public static function create( Tag $tag ) : ClassNode
    {
        if ( $tag->htmlElement->getAttribute( 'n:class' ) ) {
            throw new CompileException( 'It is not possible to combine id with n:class, or class.', $tag->position );
        }

        if ( !\class_exists( Element::class ) ) {
            throw new CompileException(
                'Latte tag `n:class` requires the ' . Element::class . '::class to be present.',
            );
        }

        $node       = new ClassNode();
        $node->args = $tag->parser->parseArguments();

        return $node;
    }

    public function print( PrintContext $context ) : string
    {
        return $context->format(
            'echo ($ʟ_tmp = ' . Element::class . '::classes(%node)) ? \' class="\' . $ʟ_tmp . \'"\' : "" %line;',
            $this->args,
            $this->position,
        );
    }

    public function &getIterator() : \Generator
    {
        yield $this->args;
    }
}