<?php

namespace Netgusto\BootCampBundle\Helper\Platform;

class ScalingoPlatform extends AbstractPaasPlatform {
    public function getPlatformName() {
        return 'Scalingo';
    }
}