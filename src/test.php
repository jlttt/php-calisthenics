<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpParser\Error;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

$code = <<<'CODE'
<?php
class Foo {
    function bar() {
        if ($baz) {
            if ($baz) {
                return true;
            }
        } else {
            return false;
        }
    }
}

CODE;

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

$traverser = new NodeTraverser();

// else
$traverser->addVisitor(new class extends NodeVisitorAbstract {
    public function enterNode(Node $node) {
        if ($node instanceof Else_) {
            var_dump('Else !');
        }
    }
});

// double depth
$traverser->addVisitor(new class extends NodeVisitorAbstract {
    public $currentFunctionLike = null;
    public $depth;
    public $classes = [
        '\PhpParser\Node\Stmt\If_',
        '\PhpParser\Node\Stmt\For_',
        '\PhpParser\Node\Stmt\ForEach_'
    ];

    const MIN_LENGTH = 3;

    public function enterNode(Node $node) {
        if ($node instanceof FunctionLike) {
            $this->currentFunctionLike = $node->name;
            $this->depth = 0;
        }

        if (isInstanceOf($node, $this->classes) && !is_null($this->currentFunctionLike)) {
            $this->depth++;
            if ($this->depth > 1) {
                var_dump($this->currentFunctionLike . ':'. $this->depth);
            }
        }

        if (property_exists($node, 'name') && is_string($node->name)) {
            if (mb_strlen($node->name) < self::MIN_LENGTH) {
                var_dump('Too short "' . $node->name . '" !!!');
            }
        }
    }

    public function leaveNode(Node $node) {
        if ($node instanceof FunctionLike) {
            $this->insideFunctionLike = false;
        }
        if (isInstanceOf($node, ['If_', 'For_', 'ForEach_']) && !is_null($this->currentFunctionLike)) {
            $this->depth--;
        }
    }
});

// namingConvention
$traverser->addVisitor(new class extends NodeVisitorAbstract {
    const MIN_LENGTH = 3;

    public function enterNode(Node $node) {

        if (property_exists($node, 'name') && is_string($node->name)) {
            if (mb_strlen($node->name) <= self::MIN_LENGTH) {
                var_dump('Too short "' . $node->name . '" !!!');
            }
        }
    }
});

try {
    $stmts = $parser->parse($code);
    // $stmts is an array of statement nodes
    $traverser->traverse($stmts);
    // $dumper = new NodeDumper;
    // echo $dumper->dump($stmts) . "\n";
} catch (Error $e) {
    echo 'Parse Error: ', $e->getMessage();
}

function isInstanceOf($obj, $classes = []) {
    foreach($classes as $class) {
        if ($obj instanceof $class) {
            return true;
        }
    }
    return false;
}
