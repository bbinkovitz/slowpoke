<?php

namespace Slowpoke\Index;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouteCollection;
use Slowpoke\Application;
use Nocarrier\Hal;
use Guzzle\Http\Client;
/**
 * Controller class for the API index.
 */
class IndexController
{

    /**
     * The current debug status.
     *
     * @var boolean
     */
    protected $debug;

    /**
     * Url Generator service.
     *
     * @var \Symfony\Component\Routing\Generator\UrlGenerator
     */
    protected $generator;

    /**
     * The collection of routes available in this application.
     *
     *
     * @var \Symfony\Component\Routing\RouteCollection
     */
    protected $routes;

    /**
     * An array of providers (modules) in the system.
     *
     * This is still a bit hacky, but we need a way for different modules to
     * expose their own link relationships etc.
     *
     * @var array
     */
    protected $providers;

    /**
     * Constructs a new controller object.
     *
     * @param boolean $debug
     *   TRUE if the application is running in debug mode, FALSE otherwise.
     */
    public function __construct(UrlGeneratorInterface $generator, RouteCollection $routes, array $providers, $debug)
    {
        $this->generator = $generator;
        $this->routes = $routes;
        $this->providers = $providers;
        $this->debug = $debug;
    }

    /**
     * Controller for the home page.
     *
     * This controller returns a HAL object containing nothing but an index
     * of top-level links/routes. Most are templated, but not all. A client
     * should be able to navigate the entire API if it starts here, and has
     * an understanding of the link relationships in use.
     *
     * @return \Nocarrier\Hal
     */
    public function index(Request $request)
    {
    $pieces = explode('&', $request->getQueryString());
    $points = array();
    foreach($pieces as $piece) {
      $params = explode('=', $piece);
      $points[$params[0]] = $params[1];
    }
      $this->api_key = 'Fmjtd|luubnu0znu%2Cb2%3Do5-9u1gdy';

      $foo = (is_numeric($points['speedlimit']));
      if (($points['start']) && $points['end'] && is_numeric($points['speedlimit'])) {
          $end = $points['end'] ? $points['end'] : NULL;
          $start = $points['start'] ? $points['start'] : NULL;
          $speed_limit = $points['speedlimit'] ? $points['speedlimit'] : NULL;
          $route_info = $this->pingMapquest($start, $end, $speed_limit);

          // If it didn't work, try it again with the bicycle parameter on.
          if(empty($route_info) || !isset($route_info->guidance->GuidanceLinkCollection)) {

                // If we don't get anything back, say sorry.
                // @todo: more useful error messages that differentiate between a bad
                // address and just not finding a route.
                // URLDecode these so we can re-use them in the error message.
                $start = urldecode($start);
                $end = urldecode($end);
                $messages = implode(' ', $route_info->info->messages);
                $message = "No route could be found from $start to $end that accommodates this speed restriction. Sorry.";
                $message .= "<br />$messages";
                return $message;
          }
          $steps = $route_info->guidance->GuidanceLinkCollection;
          // Make a list of the speed limits on this route.
          $speeds = array();
          foreach ($steps as $step) {
              $speeds[] = $step->speed;
          }

          // Tell our user what speed limits they'll encounter on the way.
          $speed_list = implode('MPH, ', array_unique($speeds));
          if (!empty($speed_list)) {
            print('Speed limits along this route: ' . $speed_list . 'MPH');
          }
          else {
            print($route_info->info->messages);
          }

          // A pretty version for demo purposes.
          return '<pre>' . print_r($route_info, 1) . '</pre>';

          // A format that GPS interfaces can use.
          return json_encode($route_info);
      }
      else {
        // If we're missing params, tell the user about it.
        $missing = (array_diff(array('start', 'end', 'speedlimit'), array_keys(array_filter($points))));
        if ((!in_array('speedlimit', $missing)) && (!is_numeric($points['speedlimit']))) {
          $missing[] = 'speedlimit';
        }
        if (!empty($missing)) {
            return('Invalid values for parameter(s): ' . implode(', ', $missing));
        }
      }
    }

    /**
     *  Get an appropriate route from Mapquest if possible.
     * @param string $url
     * @return obj
     */
    private function pingMapquest($start, $end, $speed_limit, $mode = 0) {

        // We have to initialize this to get it started.
        $avoid = array(1);
        $avoid_links = '';
        $i = 0;
        // We'll switch to bikeable routes as a fallback.
        $route_type = 'shortest';
        // Keep iterating until there's nothing left to avoid.
        while ((array_key_exists(0, $avoid)) && $i < 50) {
            /*
             * Get the navi data from mapquest.
             */
            $client = new Client("http://open.mapquestapi.com/guidance/v1/");
            // We'll add avoid links for any links that are too fast.
            $request = $client->get("route?&key=$this->api_key&from=$start&to=$end&narrativeType=text&fishbone=false&avoid=Limited%20Access&routeType=$route_type&mustAvoidLinkIds=$avoid_links");
            $response = $request->send();
            $data = json_decode($response->getBody());
              $avoid = array();
              if (!in_array('200 Unable to calculate route.', $data->info->messages)) {
                  $steps = $data->guidance->GuidanceLinkCollection;

                  // Make a list of gefIDs that are too fast for our tastes.
                  // Also get rid of those without speed (e.g. bike paths).
                  foreach ($steps as $step) {
                      if (($step->speed > $speed_limit) || empty($step->speed)) {
                        $avoid[] = $step->gefID;
                      }
                  }
                    // Find a new route without those gefIDs that we don't want.
                    $avoid_links .= implode(',', array_unique($avoid));
                }
                else if (strtolower($data->guidance->options->routeType) !== 'bicycle') {
                    $route_type = 'bicycle';
                    $avoid = array(1);
                    $avoid_links = '';
                }
                    $i++;
            }
            $count_links = (count(array_unique(explode(',', $avoid_links)))) - 1;
            print("Whew! That took $i HTTP requests! We avoided $count_links links.");
            print('<br />');
            return $data;
      }

}

