<?php

namespace Oro\Bundle\LayoutBundle\Tests\Unit\EventListener;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

use Oro\Component\Layout\LayoutContext;
use Oro\Component\Layout\ContextInterface;

use Oro\Bundle\LayoutBundle\EventListener\LayoutListener;
use Oro\Bundle\LayoutBundle\Annotation\Layout as LayoutAnnotation;

class LayoutListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $layoutManager;

    /** @var LayoutListener */
    protected $listener;

    protected function setUp()
    {
        $this->layoutManager = $this->getMockBuilder('Oro\Component\Layout\LayoutManager')
            ->disableOriginalConstructor()
            ->getMock();

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->any())
            ->method('get')
            ->with('oro_layout.layout_manager')
            ->will($this->returnValue($this->layoutManager));

        $this->listener = new LayoutListener($container);
    }

    public function testShouldNotModifyResponseWithoutLayoutAnnotation()
    {
        $this->layoutManager->expects($this->never())
            ->method('getLayoutBuilder');
        $responseEvent = $this->createResponseForControllerResultEvent([], []);
        $this->listener->onKernelView($responseEvent);
        $this->assertFalse($responseEvent->hasResponse());
    }

    public function testShouldAddOptionsFromLayoutAnnotationToContext()
    {
        $builder = $this->getMock('Oro\Component\Layout\LayoutBuilderInterface');

        $builder->expects($this->once())
            ->method('setBlockTheme')
            ->with(['blockTheme1.html.twig', 'blockTheme2.html.twig']);

        $this->setupLayoutExpectations(
            $builder,
            function (ContextInterface $context) {
                $this->assertEquals('action', $context->get('action'));
                $this->assertEquals('theme', $context->get('theme'));
                $this->assertEquals('value1', $context->get('var1'));
                $this->assertEquals('value2', $context->get('var2'));
            }
        );

        $layoutAnnotation = new LayoutAnnotation(
            [
                'action'      => 'action',
                'theme'       => 'theme',
                'blockThemes' => ['blockTheme1.html.twig', 'blockTheme2.html.twig'],
                'vars'        => ['var1', 'var2']
            ]
        );
        $responseEvent    = $this->createResponseForControllerResultEvent(
            ['_layout' => $layoutAnnotation],
            [
                'var1' => 'value1',
                'var2' => 'value2'
            ]
        );
        $this->listener->onKernelView($responseEvent);
        $this->assertEquals('Test Layout', $responseEvent->getResponse()->getContent());
    }

    public function testShouldAddBlockThemeFromLayoutAnnotation()
    {
        $builder = $this->getMock('Oro\Component\Layout\LayoutBuilderInterface');

        $builder->expects($this->once())
            ->method('setBlockTheme')
            ->with('blockTheme.html.twig');

        $this->setupLayoutExpectations($builder);

        $layoutAnnotation = new LayoutAnnotation(
            [
                'blockTheme' => 'blockTheme.html.twig'
            ]
        );
        $responseEvent    = $this->createResponseForControllerResultEvent(
            ['_layout' => $layoutAnnotation],
            []
        );
        $this->listener->onKernelView($responseEvent);
        $this->assertEquals('Test Layout', $responseEvent->getResponse()->getContent());
    }

    public function testShouldAddOneBlockThemeFromLayoutAnnotationBlockThemesAttr()
    {
        $builder = $this->getMock('Oro\Component\Layout\LayoutBuilderInterface');

        $builder->expects($this->once())
            ->method('setBlockTheme')
            ->with('blockTheme.html.twig');

        $this->setupLayoutExpectations($builder);

        $layoutAnnotation = new LayoutAnnotation(
            [
                'blockThemes' => 'blockTheme.html.twig'
            ]
        );
        $responseEvent    = $this->createResponseForControllerResultEvent(
            ['_layout' => $layoutAnnotation],
            []
        );
        $this->listener->onKernelView($responseEvent);
        $this->assertEquals('Test Layout', $responseEvent->getResponse()->getContent());
    }

    /**
     * @expectedException \Oro\Component\Layout\Exception\LogicException
     * @expectedExceptionMessage The @Template() annotation cannot be used together with the @Layout() annotation.
     */
    public function testShouldThrowExceptionIfBothLayoutAndTemplateAreUsed()
    {
        $responseEvent = $this->createResponseForControllerResultEvent(
            ['_layout' => new LayoutAnnotation([]), '_template' => new Template([])],
            []
        );
        $this->listener->onKernelView($responseEvent);
    }

    /**
     * @expectedException \Oro\Component\Layout\Exception\LogicException
     * @expectedExceptionMessage Failed to resolve the context variables. Reason: The option "unknown" does not exist.
     */
    public function testShouldThrowExceptionForMissingVarsInAnnotation()
    {
        $this->setupLayoutExpectations();

        $responseEvent = $this->createResponseForControllerResultEvent(
            ['_layout' => new LayoutAnnotation(['vars' => ['known']])],
            ['unknown' => 'data']
        );
        $this->listener->onKernelView($responseEvent);
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Component\Layout\Exception\LogicException
     * @expectedExceptionMessage Failed to resolve the context variables. Reason: The required option "required2" is missing.
     */
    // @codingStandardsIgnoreEnd
    public function testShouldThrowExceptionForNotHavingRequiredVarsWhenArrayReturned()
    {
        $this->setupLayoutExpectations();

        $attributes    = ['_layout' => new LayoutAnnotation(['vars' => ['required1', 'required2']])];
        $result        = ['required1' => 'value1'];
        $responseEvent = $this->createResponseForControllerResultEvent($attributes, $result);
        $this->listener->onKernelView($responseEvent);
    }

    // @codingStandardsIgnoreStart
    /**
     * @expectedException \Oro\Component\Layout\Exception\LogicException
     * @expectedExceptionMessage Failed to resolve the context variables. Reason: The required option "required1" is missing.
     */
    // @codingStandardsIgnoreEnd
    public function testShouldThrowExceptionForNotHavingRequiredVarsWhenContextReturned()
    {
        $this->setupLayoutExpectations();

        $attributes = ['_layout' => new LayoutAnnotation(['vars' => ['required1', 'required2']])];
        $context    = new LayoutContext();
        $context->getResolver()->setRequired(['required2']);
        $context['required2'] = 'value1';
        $responseEvent        = $this->createResponseForControllerResultEvent($attributes, $context);
        $this->listener->onKernelView($responseEvent);
    }

    // @codingStandardsIgnoreStart
    /**
     * @dataProvider             getNotEmptyAnnotationDataProvider
     *
     * @expectedException \Oro\Component\Layout\Exception\LogicException
     * @expectedExceptionMessage The empty @Layout() annotation must be used when the controller returns an instance of "Oro\Component\Layout\Layout".
     */
    // @codingStandardsIgnoreEnd
    public function testShouldThrowExceptionWhenAlreadyBuiltLayoutReturnedAndLayoutAnnotationIsNotEmpty($options)
    {
        $attributes    = ['_layout' => new LayoutAnnotation($options)];
        $layout        = $this->getMockBuilder('Oro\Component\Layout\Layout')
            ->disableOriginalConstructor()
            ->getMock();
        $responseEvent = $this->createResponseForControllerResultEvent($attributes, $layout);
        $this->listener->onKernelView($responseEvent);
    }

    public function getNotEmptyAnnotationDataProvider()
    {
        return [
            [['action' => 'action']],
            [['theme' => 'theme']],
            [['blockThemes' => ['blockTheme.html.twig']]],
            [['blockTheme' => 'blockTheme.html.twig']],
            [['vars' => ['var1']]]
        ];
    }

    public function testShouldNotOverrideActionFromLayoutAnnotation()
    {
        $this->setupLayoutExpectations(
            null,
            function (ContextInterface $context) {
                $this->assertEquals('updated_action', $context->get('action'));
            }
        );

        $layoutAnnotation = new LayoutAnnotation(
            [
                'action' => 'default_action'
            ]
        );
        $responseEvent    = $this->createResponseForControllerResultEvent(
            ['_layout' => $layoutAnnotation],
            [
                'action' => 'updated_action'
            ]
        );
        $this->listener->onKernelView($responseEvent);
        $this->assertEquals('Test Layout', $responseEvent->getResponse()->getContent());
    }

    public function testShouldNotOverrideThemeFromLayoutAnnotation()
    {
        $this->setupLayoutExpectations(
            null,
            function (ContextInterface $context) {
                $this->assertEquals('updated_theme', $context->get('theme'));
            }
        );

        $layoutAnnotation = new LayoutAnnotation(
            [
                'theme' => 'default_theme'
            ]
        );
        $responseEvent    = $this->createResponseForControllerResultEvent(
            ['_layout' => $layoutAnnotation],
            [
                'theme' => 'updated_theme'
            ]
        );
        $this->listener->onKernelView($responseEvent);
        $this->assertEquals('Test Layout', $responseEvent->getResponse()->getContent());
    }

    protected function setupLayoutExpectations($builder = null, $assertContextCallback = null)
    {
        if (null === $builder) {
            $builder = $this->getMock('Oro\Component\Layout\LayoutBuilderInterface');
        }
        $this->layoutManager->expects($this->once())
            ->method('getLayoutBuilder')
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('getLayout')
            ->willReturnCallback(
                function (ContextInterface $context) use ($assertContextCallback) {
                    $context->getResolver()
                        ->setOptional(['theme'])
                        ->setDefaults(['action' => '']);
                    $context->resolve();

                    if (null !== $assertContextCallback) {
                        call_user_func($assertContextCallback, $context);
                    }

                    $layout = $this->getMockBuilder('Oro\Component\Layout\Layout')
                        ->disableOriginalConstructor()
                        ->getMock();
                    $layout->expects($this->once())
                        ->method('render')
                        ->willReturn('Test Layout');

                    return $layout;
                }
            );
    }

    /**
     * @param array $attributes
     * @param mixed $controllerResult
     *
     * @return GetResponseForControllerResultEvent
     */
    protected function createResponseForControllerResultEvent(array $attributes, $controllerResult)
    {
        return new GetResponseForControllerResultEvent(
            $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface'),
            new Request([], [], $attributes),
            HttpKernelInterface::MASTER_REQUEST,
            $controllerResult
        );
    }
}
