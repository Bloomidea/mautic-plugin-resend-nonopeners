<?php

declare(strict_types=1);

namespace MauticPlugin\MauticResendNonOpenersBundle\Controller\Api;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\ApiBundle\Controller\CommonApiController;
use Mautic\ApiBundle\Helper\EntityResultHelper;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\AppVersion;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Model\EmailModel;
use MauticPlugin\MauticResendNonOpenersBundle\Service\NonOpenersService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @extends CommonApiController<Email>
 */
class ResendApiController extends CommonApiController
{
    public function __construct(
        CorePermissions $security,
        Translator $translator,
        EntityResultHelper $entityResultHelper,
        RouterInterface $router,
        FormFactoryInterface $formFactory,
        AppVersion $appVersion,
        RequestStack $requestStack,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        EventDispatcherInterface $dispatcher,
        CoreParametersHelper $coreParametersHelper,
    ) {
        $emailModel = $modelFactory->getModel('email');
        \assert($emailModel instanceof EmailModel);

        $this->model           = $emailModel;
        $this->entityClass     = Email::class;
        $this->entityNameOne   = 'email';
        $this->entityNameMulti = 'emails';
        $this->permissionBase  = 'email:emails';

        parent::__construct($security, $translator, $entityResultHelper, $router, $formFactory, $appVersion, $requestStack, $doctrine, $modelFactory, $dispatcher, $coreParametersHelper);
    }

    public function resendAction(NonOpenersService $nonOpenersService, int $id): Response
    {
        $entity = $this->model->getEntity($id);

        if (null === $entity) {
            return $this->notFound();
        }

        if (!$this->checkEntityAccess($entity, 'edit')) {
            return $this->accessDenied();
        }

        if (!$nonOpenersService->canResend($entity)) {
            return $this->returnError('This email cannot be resent to non-openers.', Response::HTTP_BAD_REQUEST);
        }

        try {
            $result = $nonOpenersService->resend($id);
        } catch (\LogicException|\InvalidArgumentException $e) {
            return $this->returnError($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $view = $this->view([
            'success'    => 1,
            'emailId'    => $result['emailId'],
            'segmentIds' => $result['segmentIds'],
        ], Response::HTTP_OK);

        return $this->handleView($view);
    }
}
