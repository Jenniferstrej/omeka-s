<?php
namespace OmekaTest\Job;

use Omeka\Entity\Job;
use Omeka\Job\Dispatcher;
use Omeka\Test\TestCase;

class DispatcherTest extends TestCase
{
    protected $dispatcher;

    protected $auth;

    protected $entityManager;

    protected $logger;

    public function setUp()
    {
        $strategy = $this->getMock(
            'Omeka\Job\Strategy\StrategyInterface',
            ['send', 'setServiceLocator', 'getServiceLocator']
        );

        $this->auth = $this->getMock('Zend\Authentication\AuthenticationService');
        $this->entityManager = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMock('Zend\Log\Logger', ['addWriter']);

        $this->dispatcher = new Dispatcher($strategy, $this->entityManager, $this->logger, $this->auth);
    }

    public function testGetDispatchStrategy()
    {
        $this->assertInstanceOf(
            'Omeka\Job\Strategy\StrategyInterface',
            $this->dispatcher->getDispatchStrategy()
        );
    }

    public function testDispatch()
    {
        $this->dispatcher->getDispatchStrategy()->expects($this->once())
            ->method('send')
            ->with($this->isInstanceOf('Omeka\Entity\Job'));

        $owner = $this->getMock('Omeka\Entity\User');
        $this->auth->expects($this->once())
            ->method('getIdentity')
            ->will($this->returnValue($owner));

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf('Omeka\Entity\Job'));
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->logger->expects($this->once())
            ->method('addWriter')
            ->with($this->isInstanceOf('Omeka\Log\Writer\Job'));

        $class = 'Omeka\Job\AbstractJob';
        $args = ['foo' => 'bar'];

        $job = $this->dispatcher->dispatch($class, $args);

        $this->assertInstanceOf('Omeka\Entity\Job', $job);
        $this->assertEquals(Job::STATUS_STARTING, $job->getStatus());
        $this->assertEquals($class, $job->getClass());
        $this->assertEquals($args, $job->getArgs());
        $this->assertInstanceOf('Omeka\Entity\User', $job->getOwner());
    }
}
