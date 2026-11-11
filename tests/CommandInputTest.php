<?php

namespace Mifesta\DotenvEditor\Tests;

use Mifesta\DotenvEditor\Console\Commands\DotenvSetKeyCommand;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;

class CommandInputTest extends PackageTestCase
{
    public function testValuesStayLiteralAndFalseClearsOnlyTheComment()
    {
        $command = new DotenvSetKeyCommand($this->editor());
        $this->bind($command, [
            'key' => 'APP_DEBUG',
            'value' => 'false',
            'comment' => 'false',
        ]);

        $this->withoutDeprecations(function () use ($command) {
            $this->transfer($command);
        });

        $this->assertSame('false', $this->read($command, 'value'));
        $this->assertSame('', $this->read($command, 'comment'));
        $this->assertNull($this->read($command, 'exportKey'));
        $this->assertTrue((new ReflectionClass($command))->hasProperty('restorePath'));
        $this->assertFalse((new ReflectionClass($command))->hasProperty('retorePath'));
    }

    public function testNullAndTrueArgumentsAreNotCast()
    {
        $command = new DotenvSetKeyCommand($this->editor());
        $this->bind($command, [
            'key' => 'MAIL_MAILER',
            'value' => 'null',
        ]);
        $this->transfer($command);
        $this->assertSame('null', $this->read($command, 'value'));

        $command = new DotenvSetKeyCommand($this->editor());
        $this->bind($command, [
            'key' => 'APP_DEBUG',
            'value' => 'true',
            '--export-key' => true,
        ]);
        $this->transfer($command);
        $this->assertSame('true', $this->read($command, 'value'));
        $this->assertTrue($this->read($command, 'exportKey'));
    }

    /**
     * @param DotenvSetKeyCommand $command
     * @param array $parameters
     * @return void
     */
    private function bind(DotenvSetKeyCommand $command, array $parameters)
    {
        $input = new ArrayInput($parameters, $command->getDefinition());
        $input->bind($command->getDefinition());
        $property = new ReflectionProperty($command, 'input');
        $property->setAccessible(true);
        $property->setValue($command, $input);
    }

    /**
     * @param DotenvSetKeyCommand $command
     * @return void
     */
    private function transfer(DotenvSetKeyCommand $command)
    {
        $method = new ReflectionMethod($command, 'transferInputsToProperties');
        $method->setAccessible(true);
        $method->invoke($command);
    }

    /**
     * @param DotenvSetKeyCommand $command
     * @param string $name
     * @return mixed
     */
    private function read(DotenvSetKeyCommand $command, $name)
    {
        $property = new ReflectionProperty($command, $name);
        $property->setAccessible(true);

        return $property->getValue($command);
    }
}
