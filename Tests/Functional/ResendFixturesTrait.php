<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Tests\Functional;

use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Entity\ListLead;
use MauticPlugin\MauticResendNonOpenersBundle\Service\NonOpenersService;

/**
 * Builds a segment email that has been sent to three contacts, one of whom
 * opened it, so the resend targets two non-openers.
 */
trait ResendFixturesTrait
{
    private function createSentSegmentEmail(): Email
    {
        $segment = new LeadList();
        $segment->setName('Newsletter');
        $segment->setPublicName('Newsletter');
        $segment->setAlias('newsletter');
        $segment->setIsPublished(true);
        $this->em->persist($segment);

        $email = new Email();
        $email->setName('Newsletter email');
        $email->setSubject('Newsletter');
        $email->setEmailType('list');
        $email->setIsPublished(true);
        $email->setLanguage('en');
        $email->addList($segment);
        $email->setSentCount(3);
        $this->em->persist($email);

        $translation = new Email();
        $translation->setName('Newsletter email PT');
        $translation->setSubject('Newsletter PT');
        $translation->setEmailType('list');
        $translation->setIsPublished(true);
        $translation->setLanguage('pt_PT');
        $translation->addList($segment);
        $translation->setTranslationParent($email);
        $email->addTranslationChild($translation);
        $this->em->persist($translation);

        foreach (['opener', 'non-opener-1', 'non-opener-2'] as $name) {
            $contact = new Lead();
            $contact->setEmail($name.'@example.com');
            $this->em->persist($contact);

            $membership = new ListLead();
            $membership->setLead($contact);
            $membership->setList($segment);
            $membership->setManuallyAdded(true);
            $membership->setDateAdded(new \DateTime('-1 day'));
            $this->em->persist($membership);

            $stat = new Stat();
            $stat->setEmail($email);
            $stat->setLead($contact);
            $stat->setEmailAddress($contact->getEmail());
            $stat->setDateSent(new \DateTime('-1 day'));
            $stat->setIsRead('opener' === $name);
            $this->em->persist($stat);
        }

        $this->em->flush();

        return $email;
    }

    private function getNonOpenersService(): NonOpenersService
    {
        $service = static::getContainer()->get(NonOpenersService::class);
        \assert($service instanceof NonOpenersService);

        return $service;
    }
}
