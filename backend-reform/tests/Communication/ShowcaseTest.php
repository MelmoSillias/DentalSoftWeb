<?php

namespace App\Tests\Communication;

use App\Communication\Controller\Api\ShowcaseController;
use App\Communication\Service\ShowcaseMailbox;
use App\IdentityAccess\Entity\User;
use App\Patient\Controller\ApiPortalPatient\AppreciationController;
use App\Patient\Entity\Appreciation;
use App\Patient\Entity\Patient;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ShowcaseTest extends TestCase
{
    public function testAppointmentSubjectFilter(): void
    {
        self::assertTrue(ShowcaseMailbox::isAppointmentSubject('Nouvelle demande de rendez-vous'));
        self::assertTrue(ShowcaseMailbox::isAppointmentSubject('Re: NOUVELLE DEMANDE DE RENDEZ-VOUS — Marie'));
        self::assertFalse(ShowcaseMailbox::isAppointmentSubject('Votre facture du cabinet'));
    }

    public function testAnonymousPublicExportNeverIncludesIdentityOrEmail(): void
    {
        $controller = (new \ReflectionClass(AppreciationController::class))->newInstanceWithoutConstructor();
        $map = new \ReflectionMethod($controller, 'mapPublicAppreciation');
        $appreciation = (new Appreciation())->setIsAnonymous(true)->setAuthorName('Identité privée')->setAuthorEmail('private@example.com');
        $public = $map->invoke($controller, $appreciation);
        self::assertNull($public['authorName']);
        self::assertArrayNotHasKey('authorEmail', $public);
        self::assertArrayNotHasKey('patientId', $public);
        self::assertArrayNotHasKey('consultationId', $public);
    }

    private function controller(EntityManagerInterface $em): ShowcaseController
    {
        $mailbox = (new \ReflectionClass(ShowcaseMailbox::class))->newInstanceWithoutConstructor();
        $controller = new ShowcaseController($em, $mailbox);
        $controller->setContainer(new Container());
        return $controller;
    }

    public function testResetGeneratesRandomPasswordAndPersistsOnlyHash(): void
    {
        $user = (new User())->setUsername('patient.test');
        $patient = (new Patient())->setPortalUser($user);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->with(Patient::class, 12)->willReturn($patient);
        $em->expects(self::exactly(2))->method('flush');
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->expects(self::exactly(2))->method('hashPassword')->willReturnCallback(function ($target, $plain) use ($user) {
            self::assertSame($user, $target);
            self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $plain);
            return 'hash:'.$plain;
        });
        $controller = $this->controller($em);
        $firstResponse = $controller->resetPassword(12, $hasher);
        $first = json_decode($firstResponse->getContent(), true);
        self::assertStringContainsString('no-store', $firstResponse->headers->get('Cache-Control'));
        self::assertSame('hash:'.$first['password'], $user->getPassword());
        $second = json_decode($controller->resetPassword(12, $hasher)->getContent(), true);
        self::assertNotSame($first['password'], $second['password']);
    }

    public function testInvalidUsernameDoesNotWrite(): void
    {
        $patient = (new Patient())->setPortalUser((new User())->setUsername('original'));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($patient);
        $em->expects(self::never())->method('flush');
        $response = $this->controller($em)->username(12, new Request(content: json_encode(['username' => 'x'])));
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('original', $patient->getPortalUser()->getUsername());
    }
}
