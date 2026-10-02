<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Entity;

use Mautic\CoreBundle\Entity\CommonRepository;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\LeadList;

/**
 * @extends CommonRepository<EmailResend>
 */
class EmailResendRepository extends CommonRepository
{
    public function getTableAlias(): string
    {
        return 'er';
    }

    /**
     * Check if an email has already been resent to non-openers.
     */
    public function hasBeenResent(Email $email): bool
    {
        return null !== $this->findOneBy(['originalEmail' => $email]);
    }

    /**
     * Check if an email is itself a resend.
     */
    public function isResend(Email $email): bool
    {
        return null !== $this->findOneBy(['resendEmail' => $email]);
    }

    /**
     * Get the EmailResend record for a given resend email, if any.
     */
    public function findByResendEmail(Email $email): ?EmailResend
    {
        return $this->findOneBy(['resendEmail' => $email]);
    }

    /**
     * Get the EmailResend record whose non-opener segment is the given segment,
     * if any. Records whose resend email no longer exists are ignored.
     */
    public function findByResendSegment(LeadList $segment): ?EmailResend
    {
        return $this->createQueryBuilder('er')
            ->addSelect('e')
            ->innerJoin('er.resendEmail', 'e')
            ->where('er.resendSegment = :segment')
            ->setParameter('segment', $segment)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all resends created from a given original email.
     *
     * @return EmailResend[]
     */
    public function findByOriginalEmail(Email $email): array
    {
        return $this->findBy(['originalEmail' => $email]);
    }
}
