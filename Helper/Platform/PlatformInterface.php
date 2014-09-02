<?php

namespace Symfony\BootCampBundle\Helper\Platform;

interface PlatformInterface {
    public function getPlatformName();
    public  function isLocalFileStoragePersistent();
}