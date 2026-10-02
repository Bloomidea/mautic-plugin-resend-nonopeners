<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Tests\Functional;

use Mautic\CoreBundle\Test\MauticMysqlTestCase;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\LeadList;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @see https://github.com/Bloomidea/mautic-plugin-resend-nonopeners/issues/4
 */
final class ResendSchedulingFunctionalTest extends MauticMysqlTestCase
{
    use ResendFixturesTrait;

    public function testResendIsScheduledAfterItsSegmentIsBuilt(): void
    {
        $original = $this->createSentSegmentEmail();

        $result = $this->getNonOpenersService()->resend($original->getId());

        $resend = $this->em->find(Email::class, $result['emailId']);
        \assert($resend instanceof Email);
        $this->assertNull($resend->getPublishUp(), 'The resend must not be discoverable before its segment is built.');
        $this->assertFalse($resend->getContinueSending());
        $this->assertCount(1, $resend->getTranslationChildren());
        $this->assertNull($resend->getTranslationChildren()->first()->getPublishUp());

        $segmentId = $result['segmentIds'][0];
        $this->rebuildSegment($segmentId);

        $this->em->clear();
        $resend  = $this->em->find(Email::class, $result['emailId']);
        $segment = $this->em->find(LeadList::class, $segmentId);
        \assert($resend instanceof Email && $segment instanceof LeadList);

        $publishUp     = $resend->getPublishUp();
        $lastBuiltDate = $segment->getLastBuiltDate();
        $this->assertInstanceOf(\DateTimeInterface::class, $publishUp);
        $this->assertInstanceOf(\DateTimeInterface::class, $lastBuiltDate);
        $this->assertGreaterThan($lastBuiltDate->getTimestamp(), $publishUp->getTimestamp());
        $this->assertSame('00', $publishUp->format('s'), 'publishUp must sit on a full minute to survive the email form.');

        $child = $resend->getTranslationChildren()->first();
        \assert($child instanceof Email);
        $this->assertEquals($publishUp, $child->getPublishUp());

        $emailModel = static::getContainer()->get('mautic.email.model.email');
        \assert($emailModel instanceof EmailModel);
        $this->assertSame(2, (int) $emailModel->getPendingLeads($resend, null, true));

        // Later rebuilds must leave the schedule alone.
        $this->em->clear();
        $this->rebuildSegment($segmentId);
        $this->em->clear();
        $resend = $this->em->find(Email::class, $result['emailId']);
        \assert($resend instanceof Email);
        $this->assertEquals($publishUp, $resend->getPublishUp());
    }

    /**
     * Resends created before 1.0.6 have a NULL publishUp and a segment that has
     * been rebuilt ever since. Scheduling them now would send a resend that was
     * requested long ago.
     */
    public function testResendWhoseSegmentWasAlreadyBuiltIsNotScheduled(): void
    {
        $original = $this->createSentSegmentEmail();
        $result   = $this->getNonOpenersService()->resend($original->getId());

        $segmentId = $result['segmentIds'][0];
        $segment   = $this->em->find(LeadList::class, $segmentId);
        \assert($segment instanceof LeadList);
        $segment->setLastBuiltDate(new \DateTime('-30 days'));
        // Mautic entities use explicit change tracking: flush() alone writes nothing.
        $this->em->persist($segment);
        $this->em->flush();
        $this->em->clear();

        $this->rebuildSegment($segmentId);

        $this->em->clear();
        $resend = $this->em->find(Email::class, $result['emailId']);
        \assert($resend instanceof Email);
        $this->assertNull($resend->getPublishUp());
    }

    private function rebuildSegment(int $segmentId): void
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $tester = new ApplicationTester($application);

        $exitCode = $tester->run([
            'command' => 'mautic:segments:update',
            '-i'      => $segmentId,
        ]);

        $this->assertSame(0, $exitCode, $tester->getDisplay());
    }
}
