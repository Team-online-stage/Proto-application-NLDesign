<?php

// src/Controller/BcController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Request test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class RequestController
 *
 * @Route("/bc")
 */
class BcController extends AbstractController
{
    /**
     * @Route("/user")
     * @Security("is_granted('ROLE_user')")
     * @Template
     */
    public function userAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['resources'] = $commonGroundService->getResourceList(['component'=>'bc', 'type'=>'invoices'], ['customer'=>$this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/organization")
     * @Security("is_granted('ROLE_group.admin') or is_granted('ROLE_group.organization_admin')")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['resources'] = $commonGroundService->getResourceList(['component'=>'bc', 'type'=>'invoices'], ['submitters.brp'=>$this->getUser()->getOrganization()])['hydra:member'];

        return $variables;
    }
}
