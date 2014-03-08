<?php

namespace Slowpoke\Index;

use Silex\Application;
use Slowpoke\ProviderBase;

/**
 * Provides Index-related service controllers and their routes.
 */
class Provider extends ProviderBase
{

    // From ServiceProviderInterface

    /**
     * {@inheritdoc}
     */
    function register(Application $app)
    {
        $app['index.controller'] = $app->share(function() use ($app) {
            return new IndexController($app['url_generator'], $app['routes'], $app['providers'], $app['debug']);
        });
    }

    // From ControllerProviderInterface

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {

        $controllers = $app['controllers_factory'];

        $app->get('/', 'index.controller:index')
            ->method('GET')
            ->bind('index');

        $app->get('/rels/{rel}', 'index.controller:rels')
            ->bind('index.rels');

        $app->get('/test', 'index.controller:test');

        return $controllers;
    }

}

