<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\EventListener;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\CustomButtonEvent;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Twig\Helper\ButtonHelper;
use Mautic\EmailBundle\Entity\Email;
use MauticPlugin\MauticResendNonOpenersBundle\Service\NonOpenersService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ButtonSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private RouterInterface $router,
        private CorePermissions $security,
        private NonOpenersService $nonOpenersService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CoreEvents::VIEW_INJECT_CUSTOM_BUTTONS => ['injectViewButtons', 0],
        ];
    }

    public function injectViewButtons(CustomButtonEvent $event): void
    {
        // Only on the email action pages (which includes the view page)
        [$currentRoute] = $event->getRoute(true);
        if ('mautic_email_action' !== $currentRoute) {
            return;
        }

        $email = $event->getItem();
        if (!$email instanceof Email) {
            return;
        }

        // Permission check: edit access on the email
        if (!$this->security->hasEntityAccess(
            'email:emails:editown',
            'email:emails:editother',
            $email->getCreatedBy()
        )) {
            return;
        }

        // Only show if this email is actually eligible for resend
        if (!$this->nonOpenersService->canResend($email)) {
            return;
        }

        $event->addButton(
            [
                'attr' => [
                    'class'       => 'btn btn-tertiary btn-nospin',
                    'data-toggle' => 'ajaxmodal',
                    'data-target' => '#MauticSharedModal',
                    'href'        => $this->router->generate('mautic_resend_nonopeners_modal', ['objectId' => $email->getId()]),
                    'data-header' => $this->translator->trans('mautic.resend_nonopeners.title'),
                ],
                'iconClass' => 'ri-mail-unread-line',
                'btnText'   => $this->translator->trans('mautic.resend_nonopeners.title'),
                'priority'  => 200,
            ],
            ButtonHelper::LOCATION_PAGE_ACTIONS
        );
    }
}
