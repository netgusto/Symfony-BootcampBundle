<?php

namespace Netgusto\BootCampBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class NetgustoBootCampBundle extends Bundle {

    protected $initializing = false;
    
    public function setInitializing($bool = TRUE) {
        $this->initializing = $bool;
    }

    public function isInitializing() {
        return $this->initializing;
    }
}
