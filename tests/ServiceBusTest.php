<?php
declare(strict_types=1);

namespace Lcobucci\Chimera\ServiceBus\Tactician\Tests;

use Lcobucci\Chimera\ServiceBus\Tactician\ServiceBus;
use League\Tactician\CommandBus;
use League\Tactician\Middleware;
use PHPUnit\Framework\TestCase;
use function assert;

/**
 * @coversDefaultClass \Lcobucci\Chimera\ServiceBus\Tactician\ServiceBus
 */
final class ServiceBusTest extends TestCase
{
    /**
     * @var CommandBus
     */
    private $tacticianBus;

    /**
     * @before
     */
    public function createBus(): void
    {
        $middleware = new class implements Middleware
        {
            /**
             * @param mixed $command
             *
             * @return mixed
             */
            public function execute($command, callable $next)
            {
                assert($command instanceof FetchById);

                return 'Everything good';
            }
        };

        $this->tacticianBus = new CommandBus([$middleware]);
    }

    /**
     * @test
     *
     * @covers ::__construct()
     * @covers ::handle()
     */
    public function handleShouldProcessTheMessageUsingTheDecoratedServiceAndReturnTheResult(): void
    {
        $bus = new ServiceBus($this->tacticianBus);

        self::assertSame('Everything good', $bus->handle(new FetchById(1)));
    }
}
