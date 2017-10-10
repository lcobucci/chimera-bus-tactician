<?php
declare(strict_types=1);

namespace Lcobucci\Chimera\Bus\Tactician\Tests\DependencyInjection;

use Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices;
use Lcobucci\Chimera\Bus\Tactician\DependencyInjection\Tags;
use Lcobucci\Chimera\Bus\Tactician\Tests\FetchById;
use Lcobucci\Chimera\ReadModelConverter;
use League\Tactician\Middleware;
use League\Tactician\Plugins\NamedCommand\NamedCommandExtractor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class RegisterServicesTest extends \PHPUnit\Framework\TestCase
{
    private const DEFAULT_MIDDLEWARES_PATTERN = '/^chimera\.(read_model_conversion|bus_internal)\..*(\.handler)?$/';

    private const COMMAND_BUS = 'command_bus';
    private const QUERY_BUS   = 'query_bus';

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldThrowAnExceptionIfAHandlerIsNotTaggedAsConnectedToAnyBus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = new Definition();
        $handler->addTag(Tags::HANDLER, ['handles' => FetchById::class]);

        $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            ['handler' => $handler]
        );
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldThrowAnExceptionIfAHandlerIsNotTaggedToHandleSomething(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $handler = new Definition();
        $handler->addTag(Tags::HANDLER, ['bus' => self::COMMAND_BUS]);

        $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            ['handler' => $handler]
        );
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldCreateCommandAndQueryBusesWithDefaultHandlersConnected(): void
    {
        $container = $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            []
        );

        self::assertSameHandlers($container, self::COMMAND_BUS);
        self::assertSameHandlers($container, self::QUERY_BUS);
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldCreateCommandAndQueryBusesWhichAreConnectedToTheTaggedHandlers(): void
    {
        $handler = new Definition();
        $handler->addTag(Tags::HANDLER, ['bus' => self::COMMAND_BUS, 'handles' => FetchById::class]);
        $handler->addTag(Tags::HANDLER, ['bus' => self::QUERY_BUS, 'handles' => FetchById::class]);

        $container = $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            ['handler' => $handler]
        );

        $this->assertSameHandlers($container, self::COMMAND_BUS, [FetchById::class => 'handler']);
        $this->assertSameHandlers($container, self::QUERY_BUS, [FetchById::class => 'handler']);
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldThrowAnExceptionIfAMiddlewareIsNotTaggedAsConnectedToAnyBus(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $middleware = new Definition();
        $middleware->addTag(Tags::MIDDLEWARE);

        $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            ['middleware' => $middleware]
        );
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldCreateCommandAndQueryBusesWithDefaultMiddlewaresIfContainerIsEmpty(): void
    {
        $container = $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            []
        );

        $this->assertSameDeclaredMiddlewares($container, self::COMMAND_BUS, []);
        $this->assertSameDeclaredMiddlewares($container, self::QUERY_BUS, []);
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldAlwaysCreateCommandAndQueryBusesWithAPrioritizedListOfMiddlewares(): void
    {
        $middleware1 = new Definition();
        $middleware1->addTag(Tags::MIDDLEWARE, ['bus' => self::COMMAND_BUS]);
        $middleware1->addTag(Tags::MIDDLEWARE, ['bus' => self::QUERY_BUS]);

        $middleware2 = new Definition();
        $middleware2->addTag(Tags::MIDDLEWARE, ['bus' => self::COMMAND_BUS, 'priority' => 123]);

        $container = $this->processCompilerPass(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [],
            ['middleware1' => $middleware1, 'middleware2' => $middleware2]
        );

        $this->assertSameDeclaredMiddlewares(
            $container,
            self::COMMAND_BUS,
            [new Reference('middleware2'), new Reference('middleware1')]
        );

        $this->assertSameDeclaredMiddlewares(
            $container,
            self::QUERY_BUS,
            [new Reference('middleware1')]
        );
    }

    /**
     * @dataProvider
     */
    public function provideOverridableDependencies()
    {
        return [
            'command bus with overridden inflector' => [
                self::COMMAND_BUS,
                'method_name_inflector',
                'inflector',
                2
            ],
            'query bus with overridden inflector' => [
                self::QUERY_BUS,
                'method_name_inflector',
                'inflector',
                2
            ],
            'command bus with overridden extractor' => [
                self::COMMAND_BUS,
                'class_name_extractor',
                'extractor',
                0
            ],
            'query bus with overridden extractor' => [
                self::QUERY_BUS,
                'class_name_extractor',
                'extractor',
                0
            ]
        ];
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     * @dataProvider provideOverridableDependencies
     */
    public function processShouldCreateCommandAndQueryBusesThatUsesOverriddenDependencies(
        $bus,
        $overridableDependencyId,
        $dependencyReference,
        $argument
    ): void {
        $container = new ContainerBuilder();
        $container->setDefinitions([$dependencyReference => new Definition(NamedCommandExtractor::class)]);

        $pass = new RegisterServices(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [$overridableDependencyId => $dependencyReference]
        );
        $pass->process($container);

        self::assertOverriddenDependency($container, $bus, $dependencyReference, $argument);
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldCreateCommandAndQueryBusesThatUseOverriddenConverterInsteadOfDefaultOne(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinitions(['converter' => new Definition(Middleware::class)]);

        $pass = new RegisterServices(
            self::COMMAND_BUS,
            self::QUERY_BUS,
            [
                'read_model_converter' => 'converter']
        );
        $pass->process($container);

        self::assertSame(
            $this->getInternalQueryBusConverter($container),
            $container->getDefinition('converter')
        );
    }

    /**
     * @test
     *
     * @covers \Lcobucci\Chimera\Bus\Tactician\DependencyInjection\RegisterServices
     */
    public function processShouldCreateCommandAndQueryBusesThatUseOverriddenMessageCreatorInsteadOfDefaultOne(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinitions(['creator' => new Definition(ReadModelConverter::class)]);

        $pass = new RegisterServices(self::COMMAND_BUS, self::QUERY_BUS, ['message_creator' => 'creator']);
        $pass->process($container);

        self::assertSame(
            $container->getDefinition($container->getDefinition(self::COMMAND_BUS)->getArgument(1)),
            $container->getDefinition('creator')
        );
    }

    public function processCompilerPass(
        string $commandBusId,
        string $queryBusId,
        array $dependencies = [],
        array $definitions = []
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $container->setDefinitions($definitions);

        $pass = new RegisterServices($commandBusId, $queryBusId, $dependencies);
        $pass->process($container);

        return $container;
    }

    private function assertSameDeclaredMiddlewares(
        ContainerBuilder $container,
        string $bus,
        array $expectedMiddlewares = []
    ): void {
        $bus         = $container->getDefinition($bus);
        $internalBus = $container->getDefinition((string) $bus->getArgument(0));

        $middlewareList = array_filter(
            $internalBus->getArgument(0),
            function (Reference $reference): bool {
                return preg_match(self::DEFAULT_MIDDLEWARES_PATTERN, (string) $reference) === 0;
            }
        );

        self::assertEquals($expectedMiddlewares, $middlewareList);
    }

    private function assertSameHandlers(ContainerBuilder $container, string $bus, array $handlerMap = []): void
    {
        $handlerLocator = $container->getDefinition(
            (string) $this->getHandlerMiddleware($container, $bus)->getArgument(1)
        );

        self::assertSame($handlerMap, $handlerLocator->getArgument(1));
    }

    private function assertOverriddenDependency(
        ContainerBuilder $container,
        string $bus,
        string $dependency,
        int $argument
    ): void {
        self::assertSame(
            $container->getDefinition($dependency),
            $container->getDefinition(self::getHandlerMiddleware($container, $bus)->getArgument($argument))
        );
    }

    private function getHandlerMiddleware(ContainerBuilder $container, string $bus): Definition
    {
        $internalBus = $container->getDefinition(
            (string) $container->getDefinition($bus)->getArgument(0)
        );

        $middlewareList = $internalBus->getArgument(0);

        return $container->getDefinition((string) end($middlewareList));
    }

    private function getInternalQueryBusConverter(ContainerBuilder $container): Definition
    {
        return $container->getDefinition(
            $container->getDefinition(
                $container->getDefinition(
                    $container->getDefinition('query_bus')->getArgument(0)
                )->getArgument(0)[0]
            )->getArgument(0)
        );
    }
}
