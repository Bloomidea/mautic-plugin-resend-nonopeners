<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Event\LeadListEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResendRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Schedules a resend email once its non-opener segment has been built.
 *
 * The resend is created with a NULL publishUp, which keeps the broadcast cron
 * from picking it up while the segment is still empty. mautic:segments:update
 * stamps lastBuiltDate after each full rebuild and saves the segment. On the
 * first such save, publishUp is set to just after the build, so every member
 * counts as added before the send cut-off.
 */
final class ResendSegmentSubscriber implements EventSubscriberInterface
{
    /**
     * IDs of segments whose current save records their first full build.
     *
     * @var array<int, true>
     */
    private array $firstBuilds = [];

    public function __construct(
        private EmailResendRepository $emailResendRepository,
        private EmailModel $emailModel,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::LIST_PRE_SAVE  => ['onListPreSave', 0],
            LeadEvents::LIST_POST_SAVE => ['onListPostSave', 0],
        ];
    }

    /**
     * LeadList does not track changes to lastBuiltDate, so the value it had in
     * the database is read from the unit of work before the flush. Only the
     * first build schedules a resend: resends created before 1.0.6 also have a
     * NULL publishUp, and their segments have been rebuilt for a long time.
     */
    public function onListPreSave(LeadListEvent $event): void
    {
        $segment   = $event->getList();
        $segmentId = $segment->getId();

        if (null === $segmentId || null === $segment->getLastBuiltDate()) {
            return;
        }

        $originalData = $this->entityManager->getUnitOfWork()->getOriginalEntityData($segment);

        if (null === ($originalData['lastBuiltDate'] ?? null)) {
            $this->firstBuilds[$segmentId] = true;
        } else {
            unset($this->firstBuilds[$segmentId]);
        }
    }

    public function onListPostSave(LeadListEvent $event): void
    {
        $segment   = $event->getList();
        $segmentId = $segment->getId();

        if (null === $segmentId || !isset($this->firstBuilds[$segmentId])) {
            return;
        }

        unset($this->firstBuilds[$segmentId]);

        $lastBuiltDate = $segment->getLastBuiltDate();
        $resendRecord  = $this->emailResendRepository->findByResendSegment($segment);

        if (null === $lastBuiltDate || null === $resendRecord) {
            return;
        }

        $resendEmail = $resendRecord->getResendEmail();

        // Leave alone a resend that was scheduled by hand.
        if (null !== $resendEmail->getPublishUp()) {
            return;
        }

        $publishUp = $this->roundUpToNextMinute($lastBuiltDate);

        $resendEmail->setPublishUp($publishUp);

        foreach ($resendEmail->getTranslationChildren() as $child) {
            $child->setPublishUp(clone $publishUp);
        }

        // Saving the parent also saves its translation children.
        $this->emailModel->saveEntity($resendEmail);
    }

    /**
     * The email form truncates publishUp to the minute, so a value with seconds
     * would move back on the next edit and exclude contacts added in between.
     * The strictly later minute also keeps the "date_added < publishUp" check
     * from excluding contacts added in the same second as the build.
     */
    private function roundUpToNextMinute(\DateTimeInterface $date): \DateTime
    {
        $rounded = \DateTime::createFromInterface($date);
        $rounded->setTime((int) $rounded->format('H'), (int) $rounded->format('i'));

        return $rounded->modify('+1 minute');
    }
}
