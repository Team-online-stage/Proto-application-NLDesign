<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Class UserController.
 */
class UserController extends AbstractController
{
    /**
     * @Route("/login")
     * @Template
     */
    public function login(Request $request, AuthorizationCheckerInterface $authChecker, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => getenv('APP_ID')]);

        if ($this->getUser()) {
            if (isset($application['defaultConfiguration']['configuration']['userPage'])) {
                return $this->redirect($application['defaultConfiguration']['configuration']['userPage']);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        } else {
            return $this->render('login/index.html.twig');
        }
    }

    /**
     * @Route("/digispoof")
     * @Template
     */
    public function DigispoofAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $redirect = $commonGroundService->cleanUrl(['component' => 'ds']);

        return $this->redirect($redirect.'?responceUrl='.$request->query->get('response').'&backUrl='.$request->query->get('back_url'));
    }

    /**
     * @Route("/eherkenning")
     * @Template
     */
    public function EherkenningAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, EventDispatcherInterface $dispatcher)
    {
        $redirect = $commonGroundService->cleanUrl(['component' => 'eh']);

        return $this->redirect($redirect.'?responceUrl='.$request->query->get('response').'&backUrl='.$request->query->get('back_url'));
    }

    /**
     * @Route("/logout")
     * @Template
     */
    public function logoutAction(Session $session, Request $request)
    {
        $session->set('requestType', null);
        $session->set('request', null);
        $session->set('contact', null);
        $session->set('organisation', null);

        return $this->redirect($this->generateUrl('app_default_index'));
    }
}
