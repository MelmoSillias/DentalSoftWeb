<?php

declare(strict_types=1);

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ApiRouteContractTest extends KernelTestCase
{
    public function testCanonicalAndLegacyRoutesRemainRegisteredWithTheSameControllers(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get('router');
        $routes = $router->getRouteCollection();
        $contract = json_decode(file_get_contents(dirname(__DIR__,2).'/docs/route-migration.json'),true,512,JSON_THROW_ON_ERROR);
        foreach ($contract as $row) {
            $canonical = $routes->get($row['canonicalName']);
            self::assertNotNull($canonical,$row['canonicalName']);
            self::assertSame($row['canonicalPath'],$canonical->getPath(),$row['canonicalName']);
            foreach (explode('|',$row['canonicalMethods']) as $method) self::assertContains($method,$canonical->getMethods(),$row['canonicalName']);
            if (!$row['legacyName']) continue;
            $legacy = $routes->get($row['legacyName']);
            self::assertNotNull($legacy,$row['legacyName']);
            self::assertSame($row['legacyPath'],$legacy->getPath());
            if ($row['changed'] && $legacy->getDefault('_controller')) self::assertSame($legacy->getDefault('_controller'),$canonical->getDefault('_controller'));
            if (!$row['changed']) continue;
            $params = [];
            foreach ($canonical->compile()->getPathVariables() as $variable) {
                $params[$variable] = match ($variable) { 'date'=>'2026-11-14', 'code'=>'insurer', 'action'=>'confirm', 'role'=>'admin', 'type'=>'overview', default=>41 };
            }
            foreach ($canonical->getMethods() as $method) {
                $router->getContext()->setMethod($method);
                $matched = $router->match($router->generate($row['canonicalName'],$params,UrlGeneratorInterface::ABSOLUTE_PATH));
                self::assertSame($canonical->getDefault('_controller'),$matched['_controller'] ?? null,$method.' '.$row['canonicalPath']);
            }
        }
    }
}
