<?php

namespace Netgusto\BootCampBundle\Helper\Platform;

interface PlatformInterface {
    public function getPlatformName();
    public  function isLocalFileStoragePersistent();
}