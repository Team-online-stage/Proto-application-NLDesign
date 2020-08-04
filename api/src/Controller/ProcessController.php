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
 * @Route("/process")
 */
class ProcessController extends AbstractController
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
        $variables['processes'] = $commonGroundService->getResourceList(['component' => 'ptc', 'type' => 'process_types'], ['order[name]' => 'asc'])['hydra:member'];

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/{id}/start")
     */
    public function startAction(Session $session, $id, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $session->set('request', null);

        return $this->redirect($this->generateUrl('app_process_load', ['id' => $id]));
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/{id}", defaults={"resumeRequest"="start"})
     * @Route("/{id}/{resumeRequest}", name="app_process_resume", defaults={"resumeRequest"="start"})
     * @Route("/{id}/{slug}/{resumeRequest}", name="app_process_slug", defaults={"slug"="instruction", "resumeRequest"="start"})
     * @Template
     */
    public function loadAction(Session $session, $id, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, $resumeRequest, string $slug = 'instruction')
    {
        $variables = $applicationService->getVariables();

        if (isset($resumeRequest)) {
            $variables['resumeRequest'] = $resumeRequest;

            if ($resumeRequest != 'see' && $resumeRequest != 'resume' && $resumeRequest != 'start') {
                $slug = $resumeRequest;
            }
        }

        if ($resumeRequest == 'start' || ($resumeRequest != 'start' && $resumeRequest != 'resume' && $slug == 'instruction')) {
            $variables['request'] = $session->remove('request');
        } else {
            $variables['request'] = $session->get('request', false);
        }

//        // Get former created requests from this user
//        $variables['request'] = $commonGroundService->getResourceList(['component' => 'vrc', 'type' => 'requests'], ['submitters.brp' => $variables['user']['@id'], 'order[dateCreated]'=>'desc'])['hydra:member'];

//        // If there are more then 1 we will use the last created request
//        if(!empty($variables['request']) && $variables['request'] > 0){
//            $variables['request'] = $variables['request'][0];
//        }

        if (!$variables['request']) {
            $variables['request'] = ['properties' => []];
        }

        // Defaults
        if (!array_key_exists('status', $variables['request'])) {
            $variables['request']['status'] = 'incomplete';
        }
        if (!array_key_exists('currentStage', $variables['request'])) {
            $variables['request']['currentStage'] = 'instruction';
        }

        // Let do some overwrites on the request status
        switch ($variables['request']['status']) {
            case 'submitted':
                $slug = 'submit';
                break;
            case 'in progress':
                $slug = 'in-progress';
                break;
            case 'processed':
                $slug = 'processed';
                break;
            case 'retracted':
                $slug = 'processed';
                break;
            case 'cancelled':
                $slug = 'processed';
                break;
        }
        // Let do some overwrites on the request status
        switch ($variables['request']['currentStage']) {
            case 'submit':
                $slug = 'submit';
                break;
            case 'in-progress':
                $slug = 'in-progress';
                break;
            case 'processed':
                $slug = 'processed';
                break;
            case 'retracted':
                $slug = 'processed';
                break;
            case 'cancelled':
                $slug = 'processed';
                break;
        }

        if ($request->isMethod('POST')) {
            // the second argument is the value returned when the attribute doesn't exist
            $resource = $request->request->all();

            // Merge with the request in session
            if ($session->get('request') && array_key_exists('properties', $session->get('request')) && array_key_exists('properties', $resource['request'])) {
                $request = $resource['request'];
                $request['properties'] = array_merge($session->get('request', [])['properties'], $resource['request']['properties']);
            } elseif (array_key_exists('request', $resource)) {
                $request = $resource['request'];
            } else {
                // Let retry this
                return $this->redirect($this->generateUrl('app_process_load', ['id' => $id]));
            }

            $variables['request'] = $commonGroundService->saveResource($request, ['component' => 'vrc', 'type' => 'requests']);

            // stores an attribute in the session for later reuse
            $session->set('request', $variables['request']);

            // Lets go to the next stage
            if (array_key_exists('next', $resource) && $resource['next']) {
                $stage = $commonGroundService->getResource($resource['next']);

                return $this->redirect($this->generateUrl('app_process_slug', ['id' => $id, 'slug' => $stage['slug']]));
            } else {
                return $this->redirect($this->generateUrl('app_process_load', ['id' => $id]));
            }
        }

        $variables['process'] = $commonGroundService->getResource(['component' => 'ptc', 'type' => 'process_types', 'id' => $id]);

        // Getting the current stage
        if (array_key_exists('currentStage', $variables['request']) && filter_var($variables['request']['currentStage'], FILTER_VALIDATE_URL) === true) {
            $variables['stage'] = $commonGroundService->getResource($variables['request']['currentStage']);
        } else {
            foreach ($variables['process']['stages'] as $stage) {
                if ($stage['slug'] == $slug) {
                    $variables['stage'] = $stage;
                }
            }
        }

        // Falback
        if (!array_key_exists('stage', $variables)) {
            $variables['stage'] = ['slug' => $slug];
        }

        $variables['slug'] = $slug;

        return $variables;
    }
}
