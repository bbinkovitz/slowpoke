<?php

namespace Slowpoke;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Nocarrier\Hal;

/**
 * Base class for HAL-formatting various objects.
 */
abstract class FormatterBase
{

    /**
     * Url Generator service.
     *
     * @var \Symfony\Component\Routing\Generator\UrlGenerator
     */
    protected $generator;


    /**
     * The route name that corresponds to this object.
     *
     * @var string
     */
    protected $routeName = '';

    /**
     * The placeholder name that corresponds to this object.
     *
     * @var string
     */
    protected $routePlaceholder = '';

    /**
     * Constructs a new formatter.
     *
     * @param \Symfony\Component\Routing\Generator\UrlGeneratorInterface $generator
     */
    public function __construct(UrlGeneratorInterface $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Renders an object to HAL.
     *
     * @param array $object
     *   The object to convert to HAL.
     * @param boolean $expand
     *   Whether or not to expand relevant links within the object. The meaning
     *   of this value may vary by object type.
     * @return \Nocarrier\Hal
     */
    public function toHal(array $object, $expand = false)
    {
        $hal = new Hal($this->toUri($object), $object);

        return $hal;
    }

    /**
     * Returns the URI for the specified object.
     *
     * @param type $object
     *   The object for which we want a URI.
     * @return string
     *   The URI to this object.
     */
    public function toUri($object)
    {
        return $this->generator->generate($this->routeName, array($this->routePlaceholder => $object['id']), UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Renders a list of objects to HAL.
     *
     * Use $this->rel to describe the relationship between the Hal list and its
     * objects.
     *
     * @param string $uri
     *   The URI of this list.
     * @param array $objects
     *   An array of records to include in the list.
     * @param string $rel
     *   The relation to use for all embedded resources. Defaults to 'item'.
     *
     * @return \Nocarrier\Hal
     */
    public function toHalList($uri, array $objects, $rel = 'item')
    {
        $hal = new Hal($uri);

        foreach ($objects as $obj) {
            $resource = $this->toHal($obj);
            $hal->addResource($rel, $resource);
        }

        return $hal;
    }

    /**
     * Override this method in the formatters for any resources that
     * need their paged linked results to be templated.
     *
     * @param obj $object
     *
     * @return array
     */
    public function linkAttributes($object) {
      return array();
    }

}
