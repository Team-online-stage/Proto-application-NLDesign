<?php

// src/Controller/IrcController.php

namespace App\Controller;

use App\Command\PubliccodeCommand;
use App\Service\ApplicationService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;


/**
 * Class DeveloperController
 * @package App\Controller
 * @Route("/irc")
 */
class IrcController extends AbstractController
{

//	/**
//	 * @Route("/")
//	 * @Template
//	 */
//	public function indexAction(CommonGroundService $commonGroundService, ApplicationService $applicationService)
//	{
//	    $variables = $applicationService->getVariables();
//		// Moeten we ophalen uit de ingelogde sessie
//		$person = $variables['user']['burgerservicenummer'];
//		$defaultIrc = "https://irc.huwelijksplanner.online/assents/";
//
//		$variables['assents'] = $commonGroundService->getResourceList($defaultIrc, ['person'=>$person])['hydra:member'];
//
//		return $variables;
//	}

    /**
     * @Route("assents/{id}")
     * @Template
     */
    public function assentAction($id, CommonGroundService $commonGroundService, ApplicationService $applicationService, Request $request)
    {
        $variables = $applicationService->getVariables();

        // We need need to get the assssent from a differend than standard location

        $defaultIrc = "https://irc.huwelijksplanner.online/assents/";
        $variables['assent'] = $commonGroundService->getResource($defaultIrc . $id);
        $variables['requester'] = $commonGroundService->getResource(['component' => 'vrc', 'type' => 'requests', 'id' => $variables['assent']['request']])['submitters'][0]['person'];
        $update = false;
        if (!key_exists('person', $variables['assent']) || $variables['assent']['person'] == null) {
            $variables['assent']['person'] = $variables['user']['burgerservicenummer'];
            $update = true;
        }
        if ($request->isMethod('POST') && $request->request->has('status')) {
            $variables['assent']['status'] = $request->request->get('status');

            $update = true;
//            $this->addFlash('success', "assent has been updated with status {$variables["assent"]["status"]}");
        }
        if ($update) {
            $variables['assent'] = $commonGroundService->saveResource($variables['assent']);
        }

        return $variables;
    }

    /**
     * @Route("/assents")
     * @Template
     */
    public function assentsAction($id, CommonGroundService $commonGroundService, ApplicationService $applicationService, Request $request)
    {
        $variables = $applicationService->getVariables();
//
//		// We need need to get the assssent from a differend than standard location
//
//		$defaultIrc = "https://irc.huwelijksplanner.online/assents/";
//        $variables['assent'] = $commonGroundService->getResource($defaultIrc . $id);
//        $variables['requester'] = $commonGroundService->getResource(['component'=>'vrc', 'type'=>'requests','id'=>$variables['assent']['request']])['submitters'][0]['person'];
//        $update = false;
//        if(!key_exists('person', $variables['assent']) || $variables['assent']['person'] == null){
//            $variables['assent']['person'] = $variables['user']['burgerservicenummer'];
//            $update = true;
//        }
//        if($request->isMethod('POST') && $request->request->has('status')){
//            $variables['assent']['status'] = $request->request->get('status');
//
//            $update = true;
////            $this->addFlash('success', "assent has been updated with status {$variables["assent"]["status"]}");
//        }
//        if($update){
//            $variables['assent'] = $commonGroundService->saveResource($variables['assent']);
//
//
//        }
        return $variables;
    }
}






