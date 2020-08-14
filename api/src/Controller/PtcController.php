<?php

// src/Controller/ProcessController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use DateTime;
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
 * @Route("/ptc")
 */
class PtcController extends AbstractController
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
     * This function will kick of or run a procces without a request.
     *
     * @Route("/process/{id}")
     * @Route("/process/{id}/{stage}", name="app_ptc_process_stage")
     * @Template
     */
    public function processAction(Session $session, $id, $stage = false, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['slug'] = $stage;
        $variables['submit'] = $request->query->get('submit', 'false');

        // Lets load a request
        if ($loadrequest = $request->query->get('request')) {
            $variables['request'] = $commonGroundService->getResource($loadrequest);
            $session->set('request', $variables['request']);
        }

        $variables['process'] = $commonGroundService->getResource(['component' => 'ptc', 'type' => 'process_types', 'id' => $id]);
        if ($this->getUser()) {
            $variables['requests'] = $commonGroundService->getResourceList(['component' => 'vrc', 'type' => 'requests'], ['process_type' => $variables['process']['@id'], 'submitters.brp' => $this->getUser()->getPerson(), 'order[dateCreated]'=>'desc'])['hydra:member'];
        }

        if ($stage == 'start') {
            $session->remove('request');
        }

        $variables['request'] = $session->get('request', []);

        // What if the request in session is defrend then the procces type that we are currently running? Or if we dont have a process_type at all? Then we create a base request
        if (
            (array_key_exists('processType', $variables['request']) && $variables['request']['processType'] != $variables['process']['@id'])
            ||
            !array_key_exists('processType', $variables['request'])
        ) {
            // Lets whipe the request
            $variables['request']['process_type'] = $variables['process']['@id'];
            $variables['request']['status'] = 'incomplete';
            $variables['request']['properties'] = [];
            $session->set('request', $variables['request']);
        }

        // lets handle a current stage
        if ($stage && $stage != 'start') {
            $variables['request']['currentStage'] = $stage;
        }

        // Lets make sure that we always have a stage
        if (!array_key_exists('stage', $variables) && $stage) {
            /* @todo dit is lelijk */
            foreach ($variables['process']['stages'] as $tempStage) {
                if ($tempStage['slug'] == $stage) {
                    $variables['stage'] = $tempStage;
                }
            }
        } elseif (!array_key_exists('stage', $variables)) {
            $variables['stage'] = ['next' => $variables['process']['stages'][0]];
        }

        if ($request->isMethod('POST')) {
            // the second argument is the value returned when the attribute doesn't exist
            $resource = $request->request->all();
            $files = $request->files->all();

            // Lets transfer the known properties
            $request = $resource['request'];
            if (array_key_exists('properties', $resource['request'])) {
                $properties = array_merge($variables['request']['properties'], $resource['request']['properties']);
                $request['properties'] = $properties;
            } elseif (array_key_exists('properties', $variables['request'])) {
                $request['properties'] = $variables['request']['properties'];
            }



            if (count($files)>0) {
                //We are going to need a JWT token for the DRC and ZTC here

                $token = $commonGroundService->getJwtToken('ztc');
                $commonGroundService->setHeader('Authorization', 'Bearer '.$token);
                $infoObjectTypes = $commonGroundService->getResourceList(['component'=>'ztc', 'type'=>'informatieobjecttypen'])['results'];

                $informationObjectType = null;
                foreach ($infoObjectTypes as $infoObjectType) {
                    if ($infoObjectType['omschrijving'] == 'Document') {
                        $informationObjectType = $infoObjectType['url'];
                    }
                }
                if($informationObjectType){
                    foreach($files['request']['properties'] as $key=>$file){
                        $drc['informatieobjecttype'] = $informationObjectType;
                        $drc['bronorganisatie'] = '999990482';
                        $drc['titel'] = urlencode($key);
                        $drc['auteur'] = $this->getUser()->getPerson();
                        $drc['creatiedatum'] = (new DateTime('now'))->format('Y-m-d');
                        $drc['bestandsnaam'] = $file->getClientOriginalName();
                        $drc['bestandstype'] = $file->getClientOriginalExtension();
                        $drc['formaat'] = $file->getClientMimeType();
                        $drc['taal'] = 'nld';
                        $drc['inhoud'] = base64_encode(file_get_contents($file->getPathname()));

                        $token = $commonGroundService->getJwtToken('drc');
                        $commonGroundService->setHeader('Authorization', 'Bearer '.$token);
                        $result = $commonGroundService->createResource($drc, ['component'=>'drc', 'type'=>'enkelvoudiginformatieobjecten']);
                        $request['properties'][$key] = $result['url'];
                        $commonGroundService->setHeader('Authorization', $this->getParameter('app_commonground_key'));
                    }
                }
            }

            // We only support the posting and saving of
            if ($this->getUser()) {
                $request = $commonGroundService->saveResource($request, ['component' => 'vrc', 'type' => 'requests']);
            }

            // stores an attribute in the session for later reuse
            $variables['request'] = $request;
            $session->set('request', $request);
        }

        /* lagacy */
        $variables['resource'] = $variables['request'];

        return $variables;
    }

    /**
     * This function will kick of or run a procces from a given request.
     *
     * @Route("/request/{id}")
     * @Route("/request/{id}/{stage}", name="app_ptc_request_stage", defaults={"resumeRequest"="start"})
     * @Template
     */
    public function requestAction(Session $session, $id, $stage, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = [];
        $variables['request'] = $commonGroundService->getResource(['component' => 'vrc', 'type' => 'requests', 'id' => $id]);
        $variables['procces'] = $commonGroundService->getResourceList(['component' => 'ptc', 'type' => 'process_types', 'id' => $variables['request']['process_type']]);

        if ($stage) {
            $variables['request']['currentStage'] = $stage;
        }

        return $variables;
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

            // We only support the posting and saving of
            if ($this->getUser()) {
                $request = $commonGroundService->saveResource($request, ['component' => 'vrc', 'type' => 'requests']);
            }

            // stores an attribute in the session for later reuse
            $variables['request'] = $request;
            $session->set('request', $variables['request']);

            // Lets go to the next stage
            if (array_key_exists('next', $resource) && $resource['next']) {
                $stage = $commonGroundService->getResource($resource['next']);

                return $this->redirect($this->generateUrl('app_process_slug', ['id' => $id, 'slug' => $stage['slug']]));
            } else {
                return $this->redirect($this->generateUrl('app_process_load', ['id' => $id]));
            }
        }

        $variables['ptc'] = $commonGroundService->getResource(['component' => 'ptc', 'type' => 'process_types', 'id' => $id]);

        // Lets see if we have any contact moments asociated with this processe
        if (array_key_exists('@id', $variables['request'])) {
            $variables['contactMoments'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['resources' => [$variables['request']['@id']]])['hydra:member'];
        } else {
            $variables['contactMoments'] = [];
        }

        // Getting the current stage
        if (array_key_exists('currentStage', $variables['request']) && filter_var($variables['request']['currentStage'], FILTER_VALIDATE_URL) === true) {
            $variables['stage'] = $commonGroundService->getResource($variables['request']['currentStage']);
        } else {
            foreach ($variables['ptc']['stages'] as $stage) {
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

        // We nowdays use the common decriptor resource, but request still got stuck on request. This litle line wil make sure that all widgets wil work in both worlds
        $variables['resource'] = $variables['request'];

        return $variables;
    }
}
