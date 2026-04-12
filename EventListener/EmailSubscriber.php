<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailEvent;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResendRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EmailResendRepository $emailResendRepository,
        private ListModel $listModel,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_POST_SAVE => ['onEmailPostSave', 0],
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
            $this->unpublishResendSegment($email->getId());
        }
    }

    private function unpublishResendSegment(int $emailId): void
    {
        $resendRecord = $this->emailResendRepository->findOneBy(['resendEmail' => $emailId]);

        if (null === $resendRecord) {
            return;
        }

        $segment = $resendRecord->getResendSegment();

        if (null === $segment || !$segment->isPublished()) {
            return;
        }

        $segment->setIsPublished(false);
        $this->listModel->saveEntity($segment);
    }
}
