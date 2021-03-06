<?php declare(strict_types=1);

namespace Rector\Php\Rector\If_;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BinaryOp\Spaceship;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see https://wiki.php.net/rfc/combined-comparison-operator
 * @see https://3v4l.org/LPbA0
 */
final class IfToSpaceshipRector extends AbstractRector
{
    /**
     * @var int|null
     */
    private $onEqual;

    /**
     * @var int|null
     */
    private $onSmaller;

    /**
     * @var int|null
     */
    private $onGreater;

    /**
     * @var Expr|null
     */
    private $firstValue;

    /**
     * @var Expr|null
     */
    private $secondValue;

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Changes if/else to spaceship <=> where useful', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        usort($languages, function ($a, $b) {
            if ($a[0] === $b[0]) {
                return 0;
            }

            return ($a[0] < $b[0]) ? 1 : -1;
        });
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class SomeClass
{
    public function run()
    {
        usort($languages, function ($a, $b) {
            return $b[0] <=> $a[0];
        });
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [If_::class];
    }

    /**
     * @param If_ $node
     */
    public function refactor(Node $node): ?Node
    {
        $this->reset();

        if ($node->cond instanceof Equal || $node->cond instanceof Identical) {
            if ($node->stmts[0] instanceof Return_) {
                $this->onEqual = $this->getValue($node->stmts[0]->expr);
            }
        } else {
            return null;
        }

        if ($node->else) {
            if (count($node->else->stmts) !== 1) {
                return null;
            }

            if ($node->else->stmts[0] instanceof Return_) {
                /** @var Return_ $returnNode */
                $returnNode = $node->else->stmts[0];
                if ($returnNode->expr instanceof Ternary) {
                    $this->processTernary($returnNode->expr);
                }
            }
        } else {
            $nextNode = $node->getAttribute(Attribute::NEXT_NODE);
            if ($nextNode instanceof Return_) {
                if ($nextNode->expr instanceof Ternary) {
                    $this->processTernary($nextNode->expr);
                }
            }
        }

        if ($this->firstValue === null || $this->secondValue === null) {
            return null;
        }

        if (! $this->areVariablesEqual($node->cond, $this->firstValue, $this->secondValue)) {
            return null;
        }

        // is spaceship retun values?
        if ([$this->onGreater, $this->onEqual, $this->onSmaller] !== [-1, 0, 1]) {
            return null;
        }

        if (isset($nextNode)) {
            $this->removeNode($nextNode);
        }

        // spaceship ready!
        $spaceshipNode = new Spaceship($this->secondValue, $this->firstValue);

        return new Return_($spaceshipNode);
    }

    private function areVariablesEqual(BinaryOp $node, ?Expr $firstValue, ?Expr $secondValue): bool
    {
        if ($this->areNodesEqual($node->left, $firstValue) && $this->areNodesEqual($node->right, $secondValue)) {
            return true;
        }

        if ($this->areNodesEqual($node->right, $firstValue) && $this->areNodesEqual($node->left, $secondValue)) {
            return true;
        }

        return false;
    }

    private function reset(): void
    {
        $this->onEqual = null;
        $this->onSmaller = null;
        $this->onGreater = null;

        $this->firstValue = null;
        $this->secondValue = null;
    }

    private function processTernary(Ternary $ternaryNode): void
    {
        if ($ternaryNode->cond instanceof Smaller) {
            $this->firstValue = $ternaryNode->cond->left;
            $this->secondValue = $ternaryNode->cond->right;

            $this->onSmaller = $this->getValue($ternaryNode->if);
            $this->onGreater = $this->getValue($ternaryNode->else);
        } elseif ($ternaryNode->cond instanceof Greater) {
            $this->firstValue = $ternaryNode->cond->right;
            $this->secondValue = $ternaryNode->cond->left;

            $this->onGreater = $this->getValue($ternaryNode->if);
            $this->onSmaller = $this->getValue($ternaryNode->else);
        }
    }
}
