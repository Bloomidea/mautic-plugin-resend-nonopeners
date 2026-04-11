<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Controller;

use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\EmailBundle\Model\EmailModel;
use MauticPlugin\MauticResendNonOpenersBundle\Service\NonOpenersService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ResendNonOpenersController extends CommonController
{
    public function modalAction(
        CorePermissions $security,
        EmailModel $model,
        NonOpenersService $nonOpenersService,
        Request $request,
        int $objectId,
    ): JsonResponse|Response {
        $entity = $model->getEntity($objectId);

        if (null === $entity
            || !$security->hasEntityAccess(
                'email:emails:editown',
                'email:emails:editother',
                $entity->getCreatedBy()
            )
        ) {
            return $this->postActionRedirect([
                'passthroughVars' => [
                    'closeModal' => 1,
                    'route'      => false,
                ],
            ]);
        }

        if (!$nonOpenersService->canResend($entity)) {
            $this->addFlashMessage('mautic.resend_nonopeners.error.not_eligible');

            return $this->postActionRedirect([
                'passthroughVars' => [
                    'closeModal' => 1,
                    'route'      => false,
                ],
            ]);
        }

        return $this->delegateView([
            'viewParameters' => [
                'email'     => $entity,
                'actionUrl' => $this->generateUrl('mautic_resend_nonopeners_execute', ['objectId' => $objectId]),
            ],
            'contentTemplate' => '@MauticResendNonOpeners/modal.html.twig',
        ]);
    }

    public function resendAction(
        CorePermissions $security,
        EmailModel $model,
        NonOpenersService $nonOpenersService,
        Request $request,
        int $objectId,
    ): JsonResponse|Response {
        $entity = $model->getEntity($objectId);

        if (null === $entity
            || !$security->hasEntityAccess(
                'email:emails:editown',
                'email:emails:editother',
                $entity->getCreatedBy()
            )
        ) {
            return $this->accessDenied();
        }

        try {
            $nonOpenersService->resend($objectId);
            $this->addFlashMessage('mautic.resend_nonopeners.success');
        } catch (\LogicException|\InvalidArgumentException $e) {
            $this->addFlashMessage($e->getMessage(), [], 'error', false);
        }

        $viewParameters = [
            'objectAction' => 'view',
            'objectId'     => $objectId,
        ];

        return $this->postActionRedirect([
            'returnUrl'       => $this->generateUrl('mautic_email_action', $viewParameters),
            'viewParameters'  => $viewParameters,
            'contentTemplate' => 'Mautic\EmailBundle\Controller\EmailController::viewAction',
            'passthroughVars' => [
                'mauticContent' => 'email',
                'closeModal'    => 1,
            ],
        ]);
    }
}
