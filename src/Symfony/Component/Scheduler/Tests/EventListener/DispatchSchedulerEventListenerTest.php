<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Scheduler\EventListener\DispatchSchedulerEventListener;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Tests\Messenger\SomeScheduleProvider;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

class DispatchSchedulerEventListenerTest extends TestCase
{
    public function testDispatchSchedulerEvents()
    {
        $trigger = $this->createMock(TriggerInterface::class);
        $defaultRecurringMessage = RecurringMessage::trigger($trigger, (object) ['id' => 'default']);

        $schedulerProvider = new SomeScheduleProvider([$defaultRecurringMessage]);
        $scheduleProviderLocator = $this->createMock(ContainerInterface::class);
        $scheduleProviderLocator->expects($this->once())->method('has')->willReturn(true);
        $scheduleProviderLocator->expects($this->once())->method('get')->willReturn($schedulerProvider);

        $context = new MessageContext('default', 'default', $trigger, $this->createMock(\DateTimeImmutable::class));
        $envelope = (new Envelope(new \stdClass()))->with(new ScheduledStamp($context));

        /** @var ContainerInterface $scheduleProviderLocator */
        $listener = new DispatchSchedulerEventListener($scheduleProviderLocator, $eventDispatcher = new EventDispatcher());
        $workerReceivedEvent = new WorkerMessageReceivedEvent($envelope, 'default');
        $secondListener = new TestEventListener();

        $eventDispatcher->addListener(PreRunEvent::class, [$secondListener, 'preRun']);
        $eventDispatcher->addListener(PreRunEvent::class, [$secondListener, 'postRun']);
        $listener->onMessageReceived($workerReceivedEvent);

        $this->assertTrue($secondListener->preInvoked);
        $this->assertTrue($secondListener->postInvoked);
    }
}

class TestEventListener
{
    public string $name;
    public bool $preInvoked = false;
    public bool $postInvoked = false;

    /* Listener methods */

    public function preRun($e)
    {
        $this->preInvoked = true;
    }

    public function postRun($e)
    {
        $this->postInvoked = true;
    }
}
