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
     * @Route("/subscriptions/{id}")
     * @Template
     */
    public function subscriptionAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $subscription = false, $id)
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        $variables['resource'] = $commonGroundService->getResource('https://orc.dev.zuid-drecht.nl/order_items/'.$id);

        return $variables;
    }

    /**
     * @Route("/subscriptions")
     * @Route("/subscriptions/{subscription}", name="subscription")
     * @Template
     */
    public function subscriptionsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $subscription = false)
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        if (!empty($subscription) && $subscription === true) {
            $variables['subscription'] = $commonGroundService->getResourceList('https://orc.dev.zuid-drecht.nl/order_items', ['order[dateCreated]' => 'desc', 'dateCreated[after]' => 'today', 'exists[recurrence]' => 'true', 'order[customer]' => $this->getUser()->getPerson()])['hydra:member'][0];
        } else {
            $variables['currentSubscriptions'] = $commonGroundService->getResourceList('https://orc.dev.zuid-drecht.nl/order_items?exists[recurrence]=true&dateEnd[after]&order[customer]=' . $this->getUser()->getPerson())['hydra:member'];
            $variables['availableSubscriptions'] = $commonGroundService->getResourceList('https://pdc.dev.zuid-drecht.nl/offers', ['exists[recurrence]'=>'true'])['hydra:member'];
        }

        return $variables;
    }

    /**
     * @Route("/order")
     * @Template
     */
    public function orderAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        if (!empty($session->get('order'))) {
            $variables['order'] = $session->get('order');
        } else {
            $variables['order'] = null;
        }
        if (!empty($session->get('orderItems'))) {
            $variables['orderItems'] = $session->get('orderItems');
        } else {
            $variables['orderItems'] = null;
        }

        $makeOrder = $request->request->get('make-order');

        if ($request->isMethod('POST') && empty($variables['order'])) {
            $request = $request->request->all();

            $user = $this->getUser()->getPerson();
            $userOrg = $this->getUser()->getOrganization();

            $order['name'] = 'test';
            $order['organization'] = $userOrg;
            $order['customer'] = $user;

            $order = $commonGroundService->createResource($order, 'https://orc.dev.zuid-drecht.nl/orders');

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

                if (!empty($offer['recurrence'])) {
                    $orderItem['recurrence'] = $offer['recurrence'];
                    $orderItem['dateStart'] = $today;
                }
                if (!empty($offer['notice'])) {
                    $orderItem['notice'] = $offer['notice'];
                }

                $orderItem['order'] = $order['@id'];

                $orderItem = $commonGroundService->createResource($orderItem, 'https://orc.dev.zuid-drecht.nl/order_items');
                $orderItems[] = $orderItem;
                $order = $commonGroundService->getResource($order['@id']);
                $session->set('order', $order);
                $session->set('orderItems', $orderItems);
            }
        } elseif ($request->isMethod('POST') && !empty($variables['order']) && !empty($variables['orderItems'] && $makeOrder == true)) {
            $request = $request->request->all();

            $user = $this->getUser()->getPerson();
            $userOrg = $this->getUser()->getOrganization();

            $variables['order']['organization'] = $userOrg;
            $variables['order']['customer'] = $user;

            $variables['order'] = $commonGroundService->createResource($variables['order'], 'https://orc.dev.zuid-drecht.nl/orders');

            foreach ($variables['orderItems'] as $item) {
                $item['order'] = $variables['order']['@id'];
                $item = $commonGroundService->createResource($item, 'https://orc.dev.zuid-drecht.nl/order_items');
                $variables['order']['items'][] = $item;
            }

            // If this order is not an subscription make invoice
            if (empty($variables['order']['items'][0]['recurrence'])) {
                $session->remove('order');
                $session->remove('orderItems');

                return $this->redirectToRoute('app_orc_subscriptions');
            }
        }

        if (!empty($variables['orderItems'][0]['recurrence'])) {
            return $this->redirectToRoute('app_orc_subscriptions', ['subscription' => 'true']);
        } else {
            return $variables;
        }
    }
}
