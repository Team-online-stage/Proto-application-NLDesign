<?php

// src/Controller/OrcController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;

//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
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
 * @Route("/orc")
 */
class OrcController extends AbstractController
{
    /**
     * @Route("/user")
     * @Template
     */
    public function userAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'orc', 'type' => 'orders'], ['customer' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/organization")
     * @Template
     */
    public function organizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'brc', 'type' => 'invoices'], ['submitters.brp' => $this->getUser()->getOrganization()])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/subscriptions")
     * @Template
     */
    public function subscriptionsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        $variables['currentSubscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['availabiltyStarts[exists]' => 'true', 'availabilityEnds[after]' => $today])['hydra:member'];
        $variables['availableSubscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['availabiltyStarts[exists]' => 'true', 'availabilityEnds[after]' => $today])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/order")
     */
    public function orderAction()
    {

    }
}
