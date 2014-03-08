<?php

namespace Slowpoke;

use \Silex\Application as SilexApplication;
use \Silex\ServiceProviderInterface;
use \Silex\ControllerProviderInterface;

/**
 * Base class for providers.
 *
 */
class ProviderBase implements ServiceProviderInterface, ControllerProviderInterface
{
    /**
     * {@inheritdoc}
     */
    function register(SilexApplication $app)
    {
    }

    /**
     * {@inheritdoc}
     */
    function boot(SilexApplication $app)
    {
    }

    // From ControllerProviderInterface

    /**
     * {@inheritdoc}
     */
    public function connect(SilexApplication $app)
    {
        return $app['controllers_factory'];
    }

    // Our own methods.

    /**
     * Returns definitions of relationship types provided by this provider.
     *
     * Where possible, do not define new relationships. Instead, use the IANA
     * standard Link relationships:
     *
     * @link http://www.iana.org/assignments/link-relations/link-relations.xml
     *
     * @return array
     *   A nested array defining a link relationship type.
     */
    public function linkRelationships() {
        return array();
    }

}
