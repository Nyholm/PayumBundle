<?php
namespace Payum\Bundle\PayumBundle\Tests\EventListener;

use Payum\Bundle\PayumBundle\EventListener\InteractiveRequestListener;
use Payum\Core\Bridge\Symfony\Request\ResponseInteractiveRequest;
use Payum\Core\Request\Http\PostRedirectUrlInteractiveRequest;
use Payum\Core\Request\Http\RedirectUrlInteractiveRequest;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class InteractiveRequestListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function couldBeConstructedWithoutAnyArguments()
    {
        new InteractiveRequestListener;
    }

    /**
     * @test
     */
    public function shouldDoNothingIfExceptionNotInstanceOfInteractiveRequest()
    {
        $expectedException = new Exception;
        
        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $expectedException
        );
        
        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);
        
        $this->assertNull($event->getResponse());
        $this->assertSame($expectedException, $event->getException());
        $this->assertFalse($event->isPropagationStopped());
    }

    /**
     * @test
     */
    public function shouldSetRedirectResponseIfExceptionInstanceOfRedirectUrlInteractiveRequest()
    {
        $expectedUrl = '/foo/bar';
        
        $interactiveRequest = new RedirectUrlInteractiveRequest($expectedUrl);

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $event->getResponse());
        $this->assertEquals($expectedUrl, $event->getResponse()->getTargetUrl());
        $this->assertSame($interactiveRequest, $event->getException());
    }

    /**
     * @test
     */
    public function shouldSetXStatusCodeWhenExceptionInstanceOfRedirectUrlInteractiveRequest()
    {
        $interactiveRequest = new RedirectUrlInteractiveRequest('/foo/bar');

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $event->getResponse());
        $this->assertTrue($event->getResponse()->headers->has('X-Status-Code'));
        $this->assertEquals(302, $event->getResponse()->headers->get('X-Status-Code'));
    }

    /**
     * @test
     */
    public function shouldSetResponseIfExceptionInstanceOfResponseInteractiveRequest()
    {
        $expectedResponse = new Response('foobar');

        $interactiveRequest = new ResponseInteractiveRequest($expectedResponse);

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        $this->assertSame($expectedResponse, $event->getResponse());
        $this->assertSame($interactiveRequest, $event->getException());
    }

    /**
     * @test
     */
    public function shouldSetXStatusCodeWhenExceptionInstanceOfResponseInteractiveRequest()
    {
        $expectedStatus = 555;

        $response = new Response('foobar', $expectedStatus);

        $interactiveRequest = new ResponseInteractiveRequest($response);

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        //guard
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $event->getResponse());

        $this->assertTrue($event->getResponse()->headers->has('X-Status-Code'));
        $this->assertEquals(555, $event->getResponse()->headers->get('X-Status-Code'));
    }

    /**
     * @test
     */
    public function shouldNotSetXStatusCodeIfAlreadySetWhenExceptionInstanceOfResponseInteractiveRequest()
    {
        $expectedStatus = 555;

        $response = new Response('foobar', $expectedStatus);
        $response->headers->set('X-Status-Code', 666);

        $interactiveRequest = new ResponseInteractiveRequest($response);

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        //guard
        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $event->getResponse());

        $this->assertTrue($event->getResponse()->headers->has('X-Status-Code'));
        $this->assertEquals(666, $event->getResponse()->headers->get('X-Status-Code'));
    }

    /**
     * @test
     */
    public function shouldChangeInteractiveRequestToLogicExceptionIfNotSupported()
    {
        $notSupportedInteractiveRequest = $this->getMock('Payum\Core\Request\BaseInteractiveRequest');

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $notSupportedInteractiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        $this->assertNull($event->getResponse());
        $this->assertInstanceOf('Payum\Core\Exception\LogicException', $event->getException());
        $this->assertStringStartsWith(
            'Cannot convert interactive request Mock_BaseInteractiveRequest', 
            $event->getException()->getMessage()
        );
    }

    /**
     * @test
     */
    public function shouldSetResponseIfExceptionInstanceOfPostRedirectUrlInteractiveRequest()
    {
        $interactiveRequest = new PostRedirectUrlInteractiveRequest('anUrl', array('foo' => 'foo'));

        $event = new GetResponseForExceptionEvent(
            $this->createHttpKernelMock(),
            new Request,
            'requestType',
            $interactiveRequest
        );

        $listener = new InteractiveRequestListener;

        $listener->onKernelException($event);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\Response', $event->getResponse());
        $this->assertEquals(200, $event->getResponse()->getStatusCode());
        $this->assertEquals($interactiveRequest->getContent(), $event->getResponse()->getContent());
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|HttpKernelInterface
     */
    protected function createHttpKernelMock()
    {
        return $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
    }
}