<?php

namespace Slowpoke;

use Silex\Application as SilexApplication;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Crell\ApiProblem\ApiProblem;

use Nocarrier\Hal;

// Dumb utility function; remove later.
function debug($var, $label = '')
{
    if (is_array($var) || is_object($var)) {
        $var = print_r($var, 1);
    }
    if ($label) {
        $var = "$label: $var";
    }
    print "<pre>$var</pre>\n";
}

/**
 * The Slowpoke core application.
 */
class Application extends SilexApplication
{

    /**
     * Constructs a new Application instance.
     *
     * @param array $values
     *   Additional values that should get registered on the Application.
     */
    public function __construct(array $values = array())
    {
        parent::__construct($values);

        date_default_timezone_set('UTC');

        $this->registerModuleProviders($this);

        $this->registerProviders($this);

        $this->registerServices($this);

        $this->registerOtherStuff($this);
    }

    public function registerModuleProviders($app)
    {
        $modules[] = '\Slowpoke\Index\Provider';

        $providers = array();
        foreach ($modules as $module) {
            $provider = new $module();
            $providers[] = $provider;
            $this->register($provider);
            $this->mount('/', $provider);
        }

        $app['providers'] = $providers;

    }

    /**
     * Register custom services for this application.
     *
     * Technically in Silex/Pimple "everything is a service", but this refers
     * to "service-y services", that is, not controllers and other service-esque
     * things.
     *
     * @param Application $app
     *   The application object.
     */
    protected function registerServices(Application $app)
    {

    }

    /**
     * Registers 3rd party providers on this application.
     *
     * This includes both Silex-bundled providers and 3rd party providers.
     *
     * @param Application $app
     *   The application object.
     */
    protected function registerProviders(Application $app)
    {
        // Load the installation-specific configuration file. This should never be in Git.
        $app->register(new \Igorw\Silex\ConfigServiceProvider(__DIR__."/../../config/settings.json"));

        // Load environment-specific configuration.
        $app->register(new \Igorw\Silex\ConfigServiceProvider(__DIR__."/../../config/{$app['environment']}.json"));

        // Add support for controller services.
        $app->register(new \Silex\Provider\ServiceControllerServiceProvider());

        // Add support for the UrlGenerator.
        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
    }

    /**
     * Registers other application level configuration.
     *
     * This includes before() and after() callbacks and similar.
     *
     * @todo Move everything out of this method into proper packages.
     *
     * @param Application $app
     *   The application object.
     */
    protected function registerOtherStuff(Application $app)
    {
        // Add a listener to convert a HAL object into either JSON or XML,
        // depending on the incoming mime type.
        $app['dispatcher']->addListener(KernelEvents::VIEW, function(GetResponseForControllerResultEvent $event) use ($app) {
            $result = $event->getControllerResult();

            if ($result instanceof Hal) {
                $request = $event->getRequest();
                $base = $request->getSchemeAndHttpHost() . $request->getBaseUrl();

                // Globally add Curies to all outgoing requests.
                $result->addCurie('slowpoke', $app['routes']->get('index.rels')->getPattern());

                $request = $event->getRequest();
                $types = $request->getAcceptableContentTypes();

                if (array_intersect($types, array('application/hal+json', 'application/json'))) {
                    // Treat as preferred whichever type appears first.
                    // This doesn't support specifying weight on type.
                    $key = min(array_search('application/hal+json', $types), array_search('application/json', $types));
                    $response = new HalJsonResponse($result, 200, array('content-type' => $types[$key]));
                    $response->setPretty($app['debug']);
                }
                elseif (in_array('application/hal+xml', $types)) {
                    $response = new HalXmlResponse($result);
                    $response->setPretty($app['debug']);
                }
                else {
                    // For debugging, default to returning JSON. For
                    // For production, this is an error condition.
                    if ($app['debug']) {
                        // Return application/json in dev mode because
                        // application/hal+json, while more precisely accurate,
                        // won't be rendered by most browsers.
                        $response = new Response($result->asJson(true), 200, array('Content-Type' => 'application/json'));
                    }
                    else {
                        throw new NotAcceptableHttpException("Only media types application/hal+json and application/hal+xml are supported.");
                    }
                }

                $event->setResponse($response);
            }
        });


        $app['dispatcher']->addListener(KernelEvents::VIEW, function(GetResponseForControllerResultEvent $event) use ($app) {
            $result = $event->getControllerResult();

            if (!is_string($result)) {
                return;
            }

            $output = <<<PAGE
<!DOCTYPE html>
<html>
    <head></head>
    <body>
$result
    </body>
</html>
PAGE;

            $event->setResponse(new Response($output));
        });

        // Helpful utility for generating api-problem responses.
        $app['api-problem-response'] = function() {
            return function($code, $title, $url, $message) {
                $problem = new ApiProblem($title, $url);
                $problem
                    ->setDetail($message)
                    ->setHttpStatus($code);
                $response = new Response($problem->asJson(), $code);
                $response->headers->set('Content-Type', 'application/api-problem+json, application/json');
                return $response;
            };
        };

        // Error handler for 404 errors.
        $app->error(function(ResourceNotFoundException $e, $code) use ($app) {
            return $app['api-problem-response'](404, 'Resource Not Found', 'http://httpstatus.es/404', $e->getMessage());
        }, 5);

        // Error handler for 403 errors.
        $app->error(function(AccessDeniedHttpException $e, $code) use ($app) {
            return $app['api-problem-response'](403, 'Access Denied', 'http://httpstatus.es/403', $e->getMessage());
        }, 6);

        // Error handler for 406 errors.
        $app->error(function(NotAcceptableHttpException $e, $code) use ($app) {
            return $app['api-problem-response'](406, 'Not Acceptable', 'http://httpstatus.es/406', $e->getMessage());
        }, 7);

        // Error handler for Repository not-found errors.
        $app->error(function(ObjectNotFoundException $e, $code) use ($app) {
            return $app['api-problem-response'](404, 'Resource Not Found', 'http://httpstatus.es/406', $e->getMessage());
        }, 8);

        // Generic error handler of last resort.
        // @todo This needs tests.
        $app->error(function(\Exception $e, $code) use ($app) {
            if ($app['debug']) {
                return;
            }
            return $app['api-problem-response']($code, 'Internal Server Error', 'http://httpstatus.es/500', $e->getMessage());
        }, -1);

        // Normalize GET parameters that will be used on many requests.
        // By pulling the GET parameters out into request attributes, we can
        // centrally handle their default handling and so forth here.  We can
        // also then require them as controller arguments for better
        // documentation, and in some cases don't need tot hen pass in the
        // request object.
        $app->before(function (Request $request) use ($app) {

            // There won't always be a query string, but let's pull it out
            // anyway just in case.
            if ($request->query->has('q')) {
                $request->attributes->set('query_string', $request->query->get('q', ''));
            }

        });


    }

}

