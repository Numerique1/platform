<?php

namespace Oro\Bundle\LayoutBundle\Layout\Extension;

use Psr\Log\NullLogger;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Options;

use Oro\Component\Layout\ContextInterface;
use Oro\Component\Layout\Extension\AbstractExtension;
use Oro\Component\Layout\ContextConfiguratorInterface;

use Oro\Bundle\LayoutBundle\Theme\ThemeManager;
use Oro\Bundle\LayoutBundle\Layout\Loader\FileResource;
use Oro\Bundle\LayoutBundle\Layout\Loader\LoaderInterface;
use Oro\Bundle\LayoutBundle\Layout\Loader\ResourceIterator;
use Oro\Bundle\LayoutBundle\Layout\Loader\ResourceFactoryInterface;

class ThemeExtension extends AbstractExtension implements LoggerAwareInterface, ContextConfiguratorInterface
{
    use LoggerAwareTrait;

    const PARAM_THEME = 'theme';

    /** @var Request|null */
    protected $request;

    /** @var array */
    protected $resources;

    /** @var ThemeManager */
    protected $manager;

    /** @var ResourceFactoryInterface */
    protected $factory;

    /** @var LoaderInterface */
    protected $loader;

    /** @var DependencyInitializer */
    protected $dependencyInitializer;

    /**
     * @param array                    $resources
     * @param ThemeManager             $manager
     * @param ResourceFactoryInterface $factory
     * @param LoaderInterface          $loader
     * @param DependencyInitializer    $dependencyInitializer
     */
    public function __construct(
        array $resources,
        ThemeManager $manager,
        ResourceFactoryInterface $factory,
        LoaderInterface $loader,
        DependencyInitializer $dependencyInitializer
    ) {
        $this->resources             = $resources;
        $this->manager               = $manager;
        $this->loader                = $loader;
        $this->factory               = $factory;
        $this->dependencyInitializer = $dependencyInitializer;
        $this->setLogger(new NullLogger());
    }

    /**
     * Synchronized DI method call, sets current request for further usage
     *
     * @param Request $request
     */
    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * {@inheritdoc}
     */
    public function configureContext(ContextInterface $context)
    {
        $context->getResolver()
            ->setDefaults(
                [
                    self::PARAM_THEME => function (Options $options, $value) {
                        if (null === $value && $this->request) {
                            $value = $this->request->query->get('_theme');
                            if (null === $value) {
                                $value = $this->request->attributes->get('_theme');
                            }
                        }

                        return $value;
                    }
                ]
            )
            ->setAllowedTypes([self::PARAM_THEME => ['string', 'null']]);
    }

    /**
     * {@inheritdoc}
     */
    protected function loadLayoutUpdates(ContextInterface $context)
    {
        $result    = [];
        $themeName = $context->getOr(self::PARAM_THEME);
        if ($themeName) {
            $updates = [];
            $theme   = $this->manager->getTheme($themeName);

            $path      = [$theme->getDirectory()];
            $routeName = $context->getOr(RouteContextConfigurator::PARAM_ROUTE_NAME);
            if ($routeName) {
                $path[] = $routeName;
            }

            $iterator = new ResourceIterator($this->factory, $this->resources);
            $iterator->setFilterPath($path);
            foreach ($iterator as $resource) {
                if ($this->loader->supports($resource)) {
                    $update = $this->loader->load($resource);
                    $this->dependencyInitializer->initialize($update);
                    $updates[] = $update;
                } else {
                    $this->logUnknownResource($resource);
                }
            }

            $result = ['root' => $updates];
        }

        return $result;
    }

    /**
     * @param FileResource $resource
     */
    protected function logUnknownResource(FileResource $resource)
    {
        $this->logger->notice(sprintf('Skipping resource "%s" because loader for it not found', $resource));
    }
}
