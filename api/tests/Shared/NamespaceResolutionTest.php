<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use PHPUnit\Framework\TestCase;

final class NamespaceResolutionTest extends TestCase
{
    public function testQueuedMessagesWrittenBeforeTheRefactorStillDeserialize(): void
    {
        $legacy = 'App\\Communication\\Message\\ProcessSmsQueueMessage';
        $serialized = sprintf('O:%d:"%s":1:{s:5:"limit";i:7;}',strlen($legacy),$legacy);
        $message = unserialize($serialized);
        self::assertInstanceOf(\App\Communication\Application\Message\ProcessSmsQueueMessage::class,$message);
        self::assertSame(7,$message->limit);
    }

    public function testMovedClassesKeepResolvableApplicationTypes(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__,2).'/src',\FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            if (!preg_match('/namespace\s+([^;]+);/',$source,$namespace) || !preg_match('/^(?:(?:final|abstract|readonly)\s+)*(?:class|interface|enum)\s+(\w+)/m',$source,$name)) continue;
            $class = new \ReflectionClass($namespace[1].'\\'.$name[1]);
            foreach ($class->getProperties() as $property) $this->assertResolvable($property->getType(),$class->getName().'::$'.$property->getName());
            foreach ($class->getMethods() as $method) {
                $this->assertResolvable($method->getReturnType(),$class->getName().'::'.$method->getName());
                foreach ($method->getParameters() as $parameter) $this->assertResolvable($parameter->getType(),$class->getName().'::'.$method->getName().' $'.$parameter->getName());
            }
        }
    }

    private function assertResolvable(?\ReflectionType $type, string $location): void
    {
        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            foreach ($type->getTypes() as $member) $this->assertResolvable($member,$location);
        } elseif ($type instanceof \ReflectionNamedType && str_starts_with($type->getName(),'App\\')) {
            self::assertTrue(class_exists($type->getName()) || interface_exists($type->getName()) || enum_exists($type->getName()),$location.' references '.$type->getName());
        }
    }
}
