<?php

namespace Symfony\BootCampBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymfonyBootCampBundle extends Bundle {

    protected $initializing = false;
    
    public function setInitializing($bool = TRUE) {
        $this->initializing = $bool;
    }

    public function isInitializing() {
        return $this->initializing;
    }
}
