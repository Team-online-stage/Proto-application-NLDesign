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

        $variables['currentSubscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['recurrence[exists]' => 'true', 'availabilityEnds[after]' => $today])['hydra:member'];
        $variables['availableSubscriptions'] = $commonGroundService->getResourceList(['component' => 'pdc', 'type' => 'offers'], ['exists[recurrence]' => 'true'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/order")
     * @Template
     */
    public function orderAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {

        if(!empty($session->get('order'))) {
            $variables['order'] = $session->get('order');
        } else {
            $variables['order'] = null;

        }

        if ($request->isMethod('POST')) {
            $request = $request->request->all();

            $user = $this->getUser()->getPerson();
            $userOrg = $this->getUser()->getOrganization();

            $order['name'] = 'test';
            $order['organization'] = $userOrg;
            $order['customer'] = $user;

            $order = $commonGroundService->createResource($order, ['component' => 'orc', 'type' => 'orders']);


            if (!empty($order['@id'])) {
                if (!empty($order))
                    foreach ($request['offers'] as $offer) {
                        $offer = $commonGroundService->getResource($offer);
                        $offers[] = $offer;
                        $orderItem['name'] = $offer['name'];
                        if (!empty($offer['description'])) {
                            $orderItem['description'] = $offer['description'];
                        }
                        $orderItem['offer'] = $offer['@id'];
                        if (!empty($offer['quantity'])) {
                            $orderItem['quantity'] = $offer['quantity'];
                        } else {
                            $orderItem['quantity'] = 1;
                        }
                        $orderItem['price'] = strval($offer['price']);
                        $orderItem['priceCurrency'] = $offer['priceCurrency'];


                        $orderItem['order'] = $order['@id'];

                        $orderItem = $commonGroundService->createResource($orderItem, ['component' => 'orc', 'type' => 'order_items']);
                        $order['items'][] = $orderItem['@id'];
                        $order = $commonGroundService->saveResource($order);
                        $session->set('order', $order);
                    }
            }
        }


        return $variables;
    }
}
