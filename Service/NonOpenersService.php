<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CommandHelper;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResend;
use MauticPlugin\MauticResendNonOpenersBundle\Entity\EmailResendRepository;

class NonOpenersService
{
    public function __construct(
        private EmailModel $emailModel,
        private ListModel $listModel,
        private CommandHelper $commandHelper,
        private EmailResendRepository $emailResendRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function canResend(Email $email): bool
    {
        // Always check the translation parent
        if ($translationParent = $email->getTranslationParent()) {
            $email = $translationParent;
        }

        if ('list' !== $email->getEmailType()) {
            return false;
        }

        if ('sent' !== $email->getSendingStatus()) {
            return false;
        }

        if ($this->emailResendRepository->hasBeenResent($email)) {
            return false;
        }

        if ($this->emailResendRepository->isResend($email)) {
            return false;
        }

        return true;
    }

    /**
     * @return array{emailId: int, segmentIds: list<int>}
     */
    public function resend(int $originalEmailId): array
    {
        $email = $this->emailModel->getEntity($originalEmailId);

        if (!$email instanceof Email) {
            throw new \InvalidArgumentException(sprintf('Email with ID %d not found.', $originalEmailId));
        }

        // Always work from the translation parent (segments are assigned to the parent)
        if ($translationParent = $email->getTranslationParent()) {
            $email           = $translationParent;
            $originalEmailId = $email->getId();
        }

        if (!$this->canResend($email)) {
            throw new \LogicException(sprintf('Email with ID %d cannot be resent.', $originalEmailId));
        }

        if ($email->getLists()->isEmpty()) {
            throw new \LogicException('Original email has no segments assigned.');
        }

        // Collect all email IDs to exclude (original + translation children)
        $emailIdsToExclude = [$originalEmailId];

        foreach ($email->getTranslationChildren() as $child) {
            $emailIdsToExclude[] = $child->getId();
        }

        // Create a new segment that combines:
        // 1. Membership in the original segment(s) (ensures we only target the original audience)
        // 2. "Not read email" filter (identifies non-openers)
        $originalSegmentIds   = [];
        $originalSegmentNames = [];
        foreach ($email->getLists() as $originalSegment) {
            $originalSegmentIds[]   = $originalSegment->getId();
            $originalSegmentNames[] = $originalSegment->getName();
        }

        $newSegment = new LeadList();
        $newSegment->setName(implode(', ', $originalSegmentNames).' (Non-Openers Resend, Email #'.$email->getId().')');
        $newSegment->setDescription('Auto-generated for resend to non-openers of: '.$email->getName().' (ID '.$email->getId().')');
        $newSegment->setIsGlobal(false);
        $newSegment->setIsPublished(true);

        $newSegment->setFilters([
            [
                'glue'       => 'and',
                'field'      => 'leadlist',
                'object'     => 'lead',
                'type'       => 'leadlist',
                'operator'   => 'in',
                'properties' => [
                    'filter' => $originalSegmentIds,
                ],
            ],
            [
                'glue'       => 'and',
                'field'      => 'lead_email_received',
                'object'     => 'behaviors',
                'type'       => 'lead_email_received',
                'operator'   => '!in',
                'properties' => [
                    'filter' => $emailIdsToExclude,
                ],
            ],
        ]);

        $this->listModel->saveEntity($newSegment);

        // Rebuild the segment to populate contacts
        $this->commandHelper->runCommand('mautic:segments:update', ['-i' => $newSegment->getId()]);

        // Clone the parent email
        $clonedEmail = clone $email;
        $clonedEmail->setEmailType('list');
        $clonedEmail->setName($email->getName().' (Resend - Non-Openers)');
        $clonedEmail->setLists([$newSegment]);
        $clonedEmail->setIsPublished(true);

        $this->emailModel->saveEntity($clonedEmail);

        // Clone each translation child
        foreach ($email->getTranslationChildren() as $child) {
            $clonedChild = clone $child;
            $clonedChild->setEmailType('list');
            $clonedChild->setName($child->getName().' (Resend - Non-Openers)');
            $clonedChild->setTranslationParent($clonedEmail);
            $clonedChild->setIsPublished(true);

            $this->emailModel->saveEntity($clonedChild);
        }

        // Record the resend relationship in our plugin table
        $emailResend = new EmailResend($email, $clonedEmail);
        $emailResend->setResendSegment($newSegment);
        $this->entityManager->persist($emailResend);
        $this->entityManager->flush();

        return [
            'emailId'    => $clonedEmail->getId(),
            'segmentIds' => [$newSegment->getId()],
        ];
    }
}
