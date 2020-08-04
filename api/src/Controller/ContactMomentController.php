<?php

// src/Controller/ProcessController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use function GuzzleHttp\Promise\all;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ProcessController
 *
 * @Route("/cmc")
 */
class ContactMomentController extends AbstractController
{
    /**
     * This function shows all available processes.
     *
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['received'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['receiver' => $variables['user']['person']])['hydra:member'];
        $variables['send'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['sender' => $variables['user']['person']])['hydra:member'];

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/send")
     */
    public function startAction(Session $session, $id, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['resource'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments', 'id' => $id]);

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/{id}")
     */
    public function viewAction(Session $session, $id, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['resource'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments', 'id' => $id]);

        return $variables;
    }
}
