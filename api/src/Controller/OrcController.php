<?php

// src/Controller/OrcController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
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
     * @var FlashBagInterface
     */
    private $flash;

    public function __construct(FlashBagInterface $flash)
    {
        $this->flash = $flash;
    }

    /**
     * @Route("/user")
     * @Security("is_granted('ROLE_user')")
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
     * @Security("is_granted('ROLE_scope.orc.organization.write')")
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
     * @Security("is_granted('ROLE_user')")
     * @Template
     */
    public function subscriptionAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $subscription = false, $id)
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        $variables['resource'] = $commonGroundService->getResource('https://orc.dev.zuid-drecht.nl/order_items/'.$id);

        if ($request->isMethod('POST') && !empty($request->get('cancelSub') == true)) {
            $variables['resource']['dateEnd'] = $commonGroundService->addDateInterval($today, $variables['resource']['notice']);
            $variables['resource']['dateEnd'] = date_format($variables['resource']['dateEnd'], 'Y-m-d');
//            $variables['resource'] = $commonGroundService->saveResource($variables['resource']);

            $this->flash->add('success', $variables['resource']['name'].' will end at '.$variables['resource']['dateEnd']);
        } elseif ($request->isMethod('POST') && !empty($request->get('resumeSub') == true)) {
            $variables['resource']['dateEnd'] = '';
//            $variables['resource'] = $commonGroundService->saveResource($variables['resource']);

            $this->flash->add('success', $variables['resource']['name'].' will be continued');
        }

        return $variables;
    }

    /**
     * @Route("/subscriptions")
     * @Route("/subscriptions/{subscription}", name="subscription")
     * @Security("is_granted(ROLE_user)")
     * @Template
     */
    public function subscriptionsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $subscription = false)
    {
        $today = new \DateTime('today');
        $today = date_format($today, 'Y-m-d');

        if (!empty($subscription) && $subscription === true) {
            $variables['subscription'] = $commonGroundService->getResourceList('https://orc.dev.zuid-drecht.nl/order_items', ['order[dateCreated]' => 'desc', 'dateCreated[after]' => $today, 'exists[recurrence]' => 'true', 'order[customer]' => $this->getUser()->getPerson()])['hydra:member'][0];
        } else {
            $variables['currentSubscriptions'] = $commonGroundService->getResourceList('https://orc.dev.zuid-drecht.nl/order_items', ['exists[recurrence]' => 'true', 'order.customer' => $this->getUser()->getPerson(), 'order[name]' => 'asc'])['hydra:member'];
            $variables['availableSubscriptions'] = $commonGroundService->getResourceList('https://pdc.dev.zuid-drecht.nl/offers', ['exists[recurrence]' => 'true'])['hydra:member'];
        }

        return $variables;
    }

    /**
     * @Route("/order")
     * @Security("is_granted('ROLE_scope.orc.order.write')")
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

            if (!empty($request['offers'][0])) {
                $offer = $commonGroundService->getResource($request['offers'][0]);
            } elseif (!empty($request['offer'])) {
                var_dump($request['offer']);
                exit;
                $offer = $commonGroundService->getResource($request['offer']);
            }

            $user = $this->getUser()->getPerson();
            $userOrg = $this->getUser()->getOrganization();

            $order['name'] = $offer['name'];
            $order['organization'] = $userOrg;
            $order['customer'] = $user;

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

                $orderItems[] = $orderItem;
            }

            $session->set('order', $order);
            $session->set('orderItems', $orderItems);

            $variables['order'] = $order;
            $variables['orderItems'] = $orderItems;
        } elseif ($request->isMethod('POST') && !empty($variables['order']) && !empty($variables['orderItems'] && $makeOrder == true)) {
            $request = $request->request->all();

            $user = $this->getUser()->getPerson();
            $userOrg = $this->getUser()->getOrganization();

            $variables['order']['organization'] = $userOrg;
            $variables['order']['customer'] = $user;

            //TODO: Dit moeten toch echt relatieve endpoints worden
            $variables['order'] = $commonGroundService->createResource($variables['order'], 'https://orc.dev.zuid-drecht.nl/orders');

            foreach ($variables['orderItems'] as $item) {
                $item['order'] = $variables['order']['@id'];
                $item = $commonGroundService->createResource($item, 'https://orc.dev.zuid-drecht.nl/order_items');
            }

            if (!empty($variables['order']['items'][0]['recurrence'])) {
                $session->remove('order');
                $session->remove('orderItems');

                return $this->redirectToRoute('app_orc_subscriptions');
            } else {
                return $this->redirectToRoute('app_orc_subscriptions', ['subscription' => 'true']);
            }
        }

        return $variables;
    }
}
