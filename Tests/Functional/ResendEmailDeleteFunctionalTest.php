<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResend;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @see https://github.com/Bloomidea/mautic-plugin-resend-nonopeners/issues/5
 */
final class ResendEmailDeleteFunctionalTest extends MauticMysqlTestCase
{
    use ResendFixturesTrait;

    /**
     * @return iterable<string, array{bool}>
     */
    public static function deleteModeProvider(): iterable
    {
        yield 'single delete' => [false];
        yield 'batch delete' => [true];
    }

    #[DataProvider('deleteModeProvider')]
    public function testDeletingResendEmailRetiresItsSegmentAndAllowsANewResend(bool $batch): void
    {
        $original   = $this->createSentSegmentEmail();
        $originalId = $original->getId();

        $result    = $this->getNonOpenersService()->resend($originalId);
        $segmentId = $result['segmentIds'][0];

        $emailModel = static::getContainer()->get('mautic.email.model.email');
        \assert($emailModel instanceof EmailModel);

        if ($batch) {
            $emailModel->deleteEntities([$result['emailId']]);
        } else {
            $resend = $emailModel->getEntity($result['emailId']);
            \assert($resend instanceof Email);
            $emailModel->deleteEntity($resend);
        }

        $this->em->clear();

        $segment = $this->em->find(LeadList::class, $segmentId);
        \assert($segment instanceof LeadList);
        $this->assertFalse($segment->isPublished(), 'The resend segment must stop being rebuilt.');

        $this->assertSame(0, $this->em->getRepository(EmailResend::class)->count([]));

        $original = $this->em->find(Email::class, $originalId);
        \assert($original instanceof Email);
        $this->assertTrue($this->getNonOpenersService()->canResend($original));
    }

    public function testDeletingOriginalEmailLeavesTheResendAlone(): void
    {
        $original = $this->createSentSegmentEmail();
        $result   = $this->getNonOpenersService()->resend($original->getId());

        $emailModel = static::getContainer()->get('mautic.email.model.email');
        \assert($emailModel instanceof EmailModel);
        $emailModel->deleteEntity($original);

        $this->em->clear();

        $segment = $this->em->find(LeadList::class, $result['segmentIds'][0]);
        \assert($segment instanceof LeadList);
        $this->assertTrue($segment->isPublished());
        $this->assertInstanceOf(Email::class, $this->em->find(Email::class, $result['emailId']));
    }
}
