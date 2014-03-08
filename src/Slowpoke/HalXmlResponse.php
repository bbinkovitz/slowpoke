<?php

namespace Slowpoke;

use \Symfony\Component\HttpFoundation\Request;
use \Symfony\Component\HttpFoundation\Response;

use Nocarrier\Hal;

/**
 * Response for XmlHal data.
 *
 * This response object may be called with a Hal object directly and will
 * render it to Xml and set the appropriate headers.
 *
 */
class HalXmlResponse extends Response
{

    /**
     * The Hal response we are going to send.
     *
     * @var Nocarrier\Hal
     */
    protected $hal;


    /**
     * Whether or not the Json object should be pretty-printed.
     *
     * @var boolean
     */
    protected $pretty = false;

    /**
     * Constructor.
     *
     * @param mixed   $data    The response data
     * @param integer $status  The response status code
     * @param array   $headers An array of response headers
     */
    public function __construct(Hal $hal, $status = 200, $headers = array())
    {
        parent::__construct('', $status, $headers);

        $this->hal = $hal;

        $this->headers->set('Content-Type', 'application/hal+xml, application/xml');
    }

    /**
     * {@inheritdoc}
     *
     * We need to render the HAL object before we actually prepare the response.
     */
    public function prepare(Request $request)
    {
        $this->setContent($this->hal->asXml($this->pretty));
        return parent::prepare($request);
    }

    /**
     * Sets whether or not to pretty-print the XML output for our HAL object.
     *
     * Note: This only has an effect on PHP 5.4.0 and later.
     *
     * @param boolean $pretty
     *   True if we should pretty-print the XML, false otherwise.
     */
    public function setPretty($pretty)
    {
        $this->pretty;
    }

}
