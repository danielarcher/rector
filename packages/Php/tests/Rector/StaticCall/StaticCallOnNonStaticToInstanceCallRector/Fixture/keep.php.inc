<?php

namespace Rector\Php\Tests\Rector\StaticCall\StaticCallOnNonStaticToInstanceCallRector\Fixture;

class A {
    public function method() {
        $this->foo();
    }

    public function foo()
    {

    }
}

class B extends A {
    public function method() {
        // This call is allowed, because it's not a static call (it only looks like one)
        parent::method();
        // This call is also allowed, for the same reason
        A::method();
    }
}
