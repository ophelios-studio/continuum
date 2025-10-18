<?php namespace Models\Core;

use Zephyrus\Core\Application;
use Zephyrus\Database\DatabaseBroker;

abstract class Broker extends DatabaseBroker
{
    public function __construct(private ?Application $application = null)
    {
        if (is_null($application)) {
            $application = Application::getInstance();
        }
        parent::__construct($application->getDatabase());
    }

    public function getApplication(): Application
    {
        return $this->application;
    }
}
