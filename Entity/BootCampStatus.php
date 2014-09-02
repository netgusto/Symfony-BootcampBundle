<?php

namespace Symfony\BootCampBundle\Entity;

class BootCampStatus {
    
    /**
     * @var integer
     */
    protected $id;

    protected $configuredversion;

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set configuredversion
     *
     * @param string $configuredversion
     * @return SystemStatus
     */
    public function setConfiguredversion($configuredversion)
    {
        $this->configuredversion = $configuredversion;

        return $this;
    }

    /**
     * Get configuredversion
     *
     * @return string 
     */
    public function getConfiguredversion()
    {
        return $this->configuredversion;
    }
}
