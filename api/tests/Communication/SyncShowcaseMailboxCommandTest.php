<?php

namespace App\Tests\Communication;

use App\Communication\Application\Service\NotificationService;
use App\Communication\Application\Service\ShowcaseMailbox;
use App\Communication\Infrastructure\Console\SyncShowcaseMailboxCommand;
use App\Communication\Infrastructure\Security\MailboxCredentialCipher;
use App\Communication\Infrastructure\Security\MailboxHostPolicy;
use App\Settings\Application\Service\InternetFeaturesGate;
use App\Settings\Domain\Entity\AppSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SyncShowcaseMailboxCommandTest extends TestCase
{
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/vitrine-sync-'.bin2hex(random_bytes(8));
        mkdir($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $lock = $this->cacheDir.'/showcase-mailbox.lock';
        if (is_file($lock)) {
            unlink($lock);
        }
        rmdir($this->cacheDir);
    }

    public function testOfflineModeSkipsAutomaticSyncWithoutReadingConfiguration(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $this->assertSilentSuccess($this->mailbox($em, online: false));
    }

    public function testUnconfiguredMailboxSkipsAutomaticSync(): void
    {
        $settings = $this->createMock(EntityRepository::class);
        $settings->expects(self::once())->method('findOneBy')->with(['keyName' => 'showcase_mailbox'])->willReturn(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AppSetting::class)->willReturn($settings);
        $this->assertSilentSuccess($this->mailbox($em));
    }

    public function testAutomaticSyncSkipsAnExistingSyncButManualSyncReportsIt(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getRepository');
        $mailbox = $this->mailbox($em);
        $lock = fopen($this->cacheDir.'/showcase-mailbox.lock', 'c');
        try {
            self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
            $this->assertSilentSuccess($mailbox);

            $this->expectExceptionMessage('Une synchronisation est déjà en cours.');
            $mailbox->sync();
        } finally {
            fclose($lock);
        }
    }

    private function mailbox(EntityManagerInterface $em, bool $online = true): ShowcaseMailbox
    {
        return new ShowcaseMailbox(
            $em,
            new MailboxCredentialCipher('unit-test-secret'),
            (new \ReflectionClass(NotificationService::class))->newInstanceWithoutConstructor(),
            new InternetFeaturesGate($online),
            $this->cacheDir,
            new MailboxHostPolicy([]),
        );
    }

    private function assertSilentSuccess(ShowcaseMailbox $mailbox): void
    {
        $command = new CommandTester(new SyncShowcaseMailboxCommand($mailbox));
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertSame('', $command->getDisplay());
    }
}
