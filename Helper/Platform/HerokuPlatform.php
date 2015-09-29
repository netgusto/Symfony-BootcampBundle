<?php

namespace Netgusto\BootCampBundle\Helper\Platform;

class HerokuPlatform extends AbstractPaasPlatform {
    public function getPlatformName() {
        return 'Heroku';
    }
}