<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailEvent;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResend;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResendRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EmailSubscriber implements EventSubscriberInterface
{
    /**
     * Resend records of emails being deleted, keyed by email ID. They are looked
     * up before the delete because a foreign key with ON DELETE CASCADE may
     * remove the row together with the email.
     *
     * @var array<int, EmailResend>
     */
    private array $pendingDeletes = [];

    public function __construct(
        private EmailResendRepository $emailResendRepository,
        private ListModel $listModel,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_POST_SAVE   => ['onEmailPostSave', 0],
            EmailEvents::EMAIL_PRE_DELETE  => ['onEmailPreDelete', 0],
            EmailEvents::EMAIL_POST_DELETE => ['onEmailPostDelete', 0],
        ];
    }

    public function onEmailPostSave(EmailEvent $event): void
    {
        $email   = $event->getEmail();
        $changes = $email->getChanges(true);

        // Only act when isPublished changed from true to false
        if (!isset($changes['isPublished']) || !is_array($changes['isPublished'])) {
            return;
        }

        [$oldValue, $newValue] = $changes['isPublished'];

        if ($oldValue && !$newValue) {
            $resendRecord = $this->emailResendRepository->findOneBy(['resendEmail' => $email->getId()]);

            if (null !== $resendRecord) {
                $this->unpublishResendSegment($resendRecord);
            }
        }
    }

    public function onEmailPreDelete(EmailEvent $event): void
    {
        $email = $event->getEmail();

        if (null === $email->getId()) {
            return;
        }

        $resendRecord = $this->emailResendRepository->findByResendEmail($email);

        if (null !== $resendRecord) {
            $this->pendingDeletes[$email->getId()] = $resendRecord;
        }
    }

    /**
     * Deleting a resend email retires its segment and frees the original email
     * to be resent again.
     */
    public function onEmailPostDelete(EmailEvent $event): void
    {
        $emailId = (int) ($event->getEmail()->deletedId ?? 0);

        if (!isset($this->pendingDeletes[$emailId])) {
            return;
        }

        $resendRecord = $this->pendingDeletes[$emailId];
        unset($this->pendingDeletes[$emailId]);

        $this->unpublishResendSegment($resendRecord);

        $this->entityManager->remove($resendRecord);
        $this->entityManager->flush();
    }

    private function unpublishResendSegment(EmailResend $resendRecord): void
    {
        $segment = $resendRecord->getResendSegment();

        if (null === $segment || !$segment->isPublished()) {
            return;
        }

        $segment->setIsPublished(false);
        $this->listModel->saveEntity($segment);
    }
}
