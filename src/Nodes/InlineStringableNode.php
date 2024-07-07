<?php declare ( strict_types = 1 );

namespace Northrook\Latte\Nodes;

use Generator;
use Latte\CompileException;
use Latte\Compiler;
use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;
use Latte\Runtime\HtmlStringable;


/**
 * Parsing `n:class` attributes for the {@see  Compiler\TemplateParser}
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
final class InlineStringableNode extends StatementNode
{
    public ArrayNode        $args;
    public readonly ?string $renderedString;

    /**
     * @throws CompileException
     */
    public static function create( Tag $tag ) : RenderNode {

        // Debug::dumpOnExit( $tag );

        $node       = new RenderNode();
        $node->args = $tag->parser->parseArguments();

        $callable = trim( $tag->parser->text, ' \n\r\t\v\0()' );

        if ( is_callable( $callable ) &&
             $called = ( $callable )() instanceof HtmlStringable ) {
            $node->renderedString = (string) ( $callable )();
        }
        else {
            $node->renderedString = null;
        }

        return $node;
    }

    public function print( PrintContext $context ) : string {
        // Debug::dumpOnExit( $context );
        return $context->format(
            'echo \'' . $this->renderedString . '\' %line;',
            $this->position,
        );
    }

    public function &getIterator() : Generator {
        yield $this->args;
    }
}