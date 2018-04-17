<?php
declare(strict_types=1);

namespace Lcobucci\Chimera\ServiceBus\Tactician\Tests;

use Lcobucci\Chimera\MessageCreator;
use League\Tactician\CommandBus as ServiceBus;
use League\Tactician\Middleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

abstract class MessageBusTestCase extends TestCase
{
    protected function createMessageCreator(
        string $message,
        ServerRequestInterface $request,
        object $createdObject
    ): MessageCreator {
        $messageCreator = $this->createMock(MessageCreator::class);

        $messageCreator->expects($this->once())
                       ->method('create')
                       ->with($message, $request)
                       ->willReturn($createdObject);

        return $messageCreator;
    }

    protected function createServiceBus(callable $callback): ServiceBus
    {
        return new ServiceBus(
            [
                new class ($callback) implements Middleware
                {
                    /**
                     * @var callable
                     */
                    private $callback;

                    public function __construct(callable $callback)
                    {
                        $this->callback = $callback;
                    }

                    /**
                     * @param object|mixed $command
                     *
                     * @return mixed
                     */
                    public function execute($command, callable $next)
                    {
                        return ($this->callback)($command);
                    }
                },
            ]
        );
    }
}
