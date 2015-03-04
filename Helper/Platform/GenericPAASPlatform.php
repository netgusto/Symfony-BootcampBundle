<?php

namespace Symfony\BootCampBundle\Helper\Platform;

class GenericPAASPlatform extends AbstractPaasPlatform {
    public function getPlatformName() {
        return 'Generic PAAS platform';
    }
}