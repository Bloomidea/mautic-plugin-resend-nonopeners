<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\EmailBundle\Entity\Email;
use Mautic\LeadBundle\Entity\LeadList;

class EmailResend
{
    private ?int $id = null;

    private Email $originalEmail;

    private Email $resendEmail;

    private ?LeadList $resendSegment = null;

    private \DateTimeInterface $dateAdded;

    public function __construct(Email $originalEmail, Email $resendEmail)
    {
        $this->originalEmail = $originalEmail;
        $this->resendEmail   = $resendEmail;
        $this->dateAdded     = new \DateTime();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('email_resends')
            ->setCustomRepositoryClass(EmailResendRepository::class)
            ->addUniqueConstraint(['resend_email_id'], 'uniq_resend_email_id')
            ->addIndex(['original_email_id'], 'idx_original_email_id');

        $builder->addId();

        $builder->createManyToOne('originalEmail', Email::class)
            ->addJoinColumn('original_email_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createManyToOne('resendEmail', Email::class)
            ->addJoinColumn('resend_email_id', 'id', false, false, 'CASCADE')
            ->build();

        $builder->createManyToOne('resendSegment', LeadList::class)
            ->addJoinColumn('resend_segment_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->addNamedField('dateAdded', 'datetime', 'date_added');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalEmail(): Email
    {
        return $this->originalEmail;
    }

    public function getResendEmail(): Email
    {
        return $this->resendEmail;
    }

    public function getResendSegment(): ?LeadList
    {
        return $this->resendSegment;
    }

    public function setResendSegment(?LeadList $resendSegment): self
    {
        $this->resendSegment = $resendSegment;

        return $this;
    }

    public function getDateAdded(): \DateTimeInterface
    {
        return $this->dateAdded;
    }
}
