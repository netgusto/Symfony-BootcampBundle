<?php

namespace Symfony\BootCampBundle\Helper\Platform;

class HerokuPlatform extends AbstractPaasPlatform {
    public function getPlatformName() {
        return 'Heroku';
    }
}