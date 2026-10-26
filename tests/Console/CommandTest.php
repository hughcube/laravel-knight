<?php

namespace HughCube\Laravel\Knight\Tests\Console;

use HughCube\Laravel\Knight\Console\Command;
use HughCube\Laravel\Knight\Tests\TestCase;
use Illuminate\Support\Collection;

class CommandTest extends TestCase
{
    private function makeCommand(array $options, $askResponse, $confirmResponse): Command
    {
        return new class($options, $askResponse, $confirmResponse) extends Command
        {
            protected $signature = 'knight:test-command';

            public array $optionsValues = [];
            public array $asked = [];
            public array $confirmed = [];
            public $askResponse;
            public $confirmResponse;

            public function __construct(array $options, $askResponse, $confirmResponse)
            {
                parent::__construct();

                $this->optionsValues = $options;
                $this->askResponse = $askResponse;
                $this->confirmResponse = $confirmResponse;
            }

            public function option($key = null)
            {
                return $this->optionsValues[$key] ?? null;
            }

            public function ask($question, $default = null)
            {
                $this->asked = [$question, $default];

                return $this->askResponse;
            }

            public function confirm($question, $default = false)
            {
                $this->confirmed = [$question, $default];

                return $this->confirmResponse;
            }

            public function callGetOrAskOption($name, $question, $default = null)
            {
                return $this->getOrAskOption($name, $question, $default);
            }

            public function callGetOrAskOptionIds($name, $question, $default = null): Collection
            {
                return $this->getOrAskOptionIds($name, $question, $default);
            }

            public function callGetOrAskBoolOption($name, $question, $default = false): bool
            {
                return $this->getOrAskBoolOption($name, $question, $default);
            }
        };
    }

    public function testGetOrAskOptionReturnsOption()
    {
        $command = $this->makeCommand(['foo' => 'bar'], 'asked', true);

        $this->assertSame('bar', $command->callGetOrAskOption('foo', 'question?', 'default'));
        $this->assertSame([], $command->asked);
    }

    public function testGetOrAskOptionAsksWhenMissing()
    {
        $command = $this->makeCommand(['foo' => ''], 'asked', true);

        $this->assertSame('asked', $command->callGetOrAskOption('foo', 'question?', 'default'));
        $this->assertSame(['question?', 'default'], $command->asked);
    }

    public function testGetOrAskOptionIdsSplitsValues()
    {
        $command = $this->makeCommand(['ids' => '1,2,3'], 'asked', true);

        $ids = $command->callGetOrAskOptionIds('ids', 'question?', 'default');

        $this->assertSame(['1', '2', '3'], $ids->toArray());
    }

    public function testGetOrAskBoolOptionUsesOptionValue()
    {
        $command = $this->makeCommand(['flag' => 'true'], 'asked', false);

        $this->assertTrue($command->callGetOrAskBoolOption('flag', 'question?', false));
        $this->assertSame([], $command->confirmed);
    }

    public function testGetOrAskBoolOptionUsesConfirm()
    {
        $command = $this->makeCommand(['flag' => null], 'asked', true);

        $this->assertTrue($command->callGetOrAskBoolOption('flag', 'question?', true));
        $this->assertSame(['question?', true], $command->confirmed);
    }
}
