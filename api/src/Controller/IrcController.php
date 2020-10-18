<?php

// src/Controller/IrcController.php

namespace App\Controller;

//use App\Command\PubliccodeCommand;
use App\Service\ApplicationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class IrcController.
 *
 * @Route("/irc")
 */
class IrcController extends AbstractController
{
//    /**
//     * @Route("/")
//     * @Template
//     */
//    public function indexAction(CommonGroundService $commonGroundService, ApplicationService $applicationService)
//    {
//        $variables = [];
//
    //	    $variables = $applicationService->getVariables();
    //		// Moeten we ophalen uit de ingelogde sessie
    //		$person = $variables['user']['burgerservicenummer'];
    //		$defaultIrc = "https://irc.huwelijksplanner.online/assents/";
//
    //		$variables['assents'] = $commonGroundService->getResourceList($defaultIrc, ['person'=>$person])['hydra:member'];
//
//        return $variables;
//    }

    /**
     * @Route("/assents/{id}")
     * @Template
     */
    public function assentAction($id, CommonGroundService $commonGroundService, ApplicationService $applicationService, Request $request)
    {
        $variables = [];
        $variables['assent'] = $commonGroundService->getResource(['component' => 'irc', 'type' => 'assents', 'id' => $id]);

        // We need need to get the assent from a different than standard location
        if (!empty($this->getUser())) {
            $defaultIrc = 'https://irc.huwelijksplanner.online/assents/';
            $variables['user'] = $commonGroundService->getResource($this->getUser()->getPerson());
            if (!empty($variables['assent']['requester'])) {
                $variables['requester'] = $commonGroundService->getResource($variables['assent']['requester']);
            }
            $update = false;
            if (!key_exists('person', $variables['assent']) || $variables['assent']['person'] == null) {
                $variables['assent']['person'] = $variables['user']['burgerservicenummer'];
                $update = true;
            }
            if ($request->isMethod('POST') && $request->request->has('status')) {
                $variables['assent']['status'] = $request->request->get('status');

                $update = true;
                $this->addFlash('success', "assent has been updated with status {$variables['assent']['status']}");
            }
            if ($update) {
                $variables['assent'] = $commonGroundService->saveResource($variables['assent'], ['component' => 'irc', 'type' => 'assents']);
            }
        }

        return $variables;
    }

    /**
     * @Route("/assents")
     * @Security("is_granted('ROLE_scope.irc.assent.write')")
     * @Template
     */
    public function assentsAction(CommonGroundService $commonGroundService, ApplicationService $applicationService, Request $request)
    {
        $variables = [];

        if (!empty($this->getUser())) {
            $variables['user'] = $commonGroundService->getResource($this->getUser()->getPerson());
            $user['person@id'] = $this->getUser()->getPerson();
            $variables['assents'] = $commonGroundService->getResourceList(['component' => 'irc', 'type' => 'assents'], ['person' => $variables['user']['burgerservicenummer']])['hydra:member'];
        }

        return $variables;
    }
}
