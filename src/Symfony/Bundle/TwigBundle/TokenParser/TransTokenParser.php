<?php

namespace Symfony\Bundle\TwigBundle\TokenParser;

use Symfony\Bundle\TwigBundle\Node\TransNode;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * 
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class TransTokenParser extends \Twig_TokenParser
{
    /**
     * Parses a token and returns a node.
     *
     * @param  \Twig_Token $token A Twig_Token instance
     *
     * @return \Twig_NodeInterface A Twig_NodeInterface instance
     */
    public function parse(\Twig_Token $token)
    {
        $lineno = $token->getLine();
        $stream = $this->parser->getStream();

        $vars = null;
        $domain = new \Twig_Node_Expression_Constant('messages', $lineno);
        if (!$stream->test(\Twig_Token::BLOCK_END_TYPE)) {
            $body = null;
            if (!$stream->test('from')) {
                // {% trans "message" %}
                // {% trans message %}
                $body = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('with')) {
                // {% trans "message" with vars %}
                $stream->next();
                $vars = $this->parser->getExpressionParser()->parseExpression();
            }

            if ($stream->test('from')) {
                // {% trans "message" from "messages" %}
                $stream->next();
                $domain = $this->parser->getExpressionParser()->parseExpression();

                // {% trans from "messages" %}message{% endtrans %}
                if (null === $body) {
                    $stream->expect(\Twig_Token::BLOCK_END_TYPE);
                    $body = $this->parser->subparse(array($this, 'decideTransFork'), true);
                }
            } elseif (!$stream->test(\Twig_Token::BLOCK_END_TYPE)) {
                throw new \Twig_SyntaxError(sprintf('Unexpected token. Twig was looking for the "from" keyword line %s)', $lineno), -1);
            }
        } else {
            // {% trans %}message{% endtrans %}
            $stream->expect(\Twig_Token::BLOCK_END_TYPE);
            $body = $this->parser->subparse(array($this, 'decideTransFork'), true);
        }

        $stream->expect(\Twig_Token::BLOCK_END_TYPE);

        return new TransNode($body, $domain, null, $vars, $this->isSimpleString($body), $lineno, $this->getTag());
    }

    public function decideTransFork($token)
    {
        return $token->test(array('endtrans'));
    }

    protected function isSimpleString(\Twig_NodeInterface $body)
    {
        if (0 === count($body)) {
            return false;
        }

        foreach ($body as $i => $node) {
            if (
                $node instanceof \Twig_Node_Text
                ||
                ($node instanceof \Twig_Node_Print && $node->expr instanceof \Twig_Node_Expression_Name)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Gets the tag name associated with this token parser.
     *
     * @param string The tag name
     */
    public function getTag()
    {
        return 'trans';
    }
}
