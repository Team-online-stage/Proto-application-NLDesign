<?php

// src/Controller/ZZController.php

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
 * @Route("/request")
 */
class VrcController extends AbstractController
{
    /**
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = $applicationService->getVariables();
        $variables['requests'] = $commonGroundService->getResourceList(['component'=>'vrc', 'type'=>'requests'], ['submitters.brp'=>$variables['user']['@id']])['hydra:member'];
//        var_dump($variables['requests']);

        // Lets provide this data to the template
        return $variables;
    }

    /**
     * @Route("/load/{id}/{resumeRequest}", defaults={"resumeRequest"="start"})
     */
    public function loadAction($id, Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $resumeRequest)
    {

        //$variables = $applicationService->getVariables();
        $loadedRequest = $commonGroundService->getResourceList(['component'=>'vrc', 'type'=>'requests', 'id'=>$id], ['extend'=>'processType']);

        $session->set('request', $loadedRequest);
        if (isset($resumeRequest)) {
            return $this->redirect($this->generateUrl('app_process_resume', ['id'=>$loadedRequest['processType']['id'], 'resumeRequest'=>$resumeRequest]));
        }

        return $this->redirect($this->generateUrl('app_process_load', ['id'=>$loadedRequest['processType']['id']]));
    }
}
