<?php

// src/Controller/ZuidDrechtController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use phpDocumentor\Reflection\Type;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The Tender test handles any calls for tenders.
 *
 * Class TenderController
 *
 * @Route("/chrc")
 */
class TenderController extends AbstractController
{
    /**
     * @Route("/new-pitch")
     * @Template
     */
    public function newpitchAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
//        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/new-pitch']); // Lets see if there is a post to procces

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            $resource['submitters'][] = $variables['user']['@id'];
            $resource['dateSubmitted'] = '2020-07-16T12:00:01+00:00';

            $resource['submitter'] = $variables['user']['@id'];
            $resource['dateSubmitted'] = '2020-07-16T12:00:01+00:00';

//            var_dump($resource);

            $resource = $commonGroundService->createResource($resource, ['component' => 'chrc', 'type' => 'pitches']);

            $id = $resource['id'];

            return $this->redirectToRoute('app_tender_pitch', ['id' => $id]);

//                if (key_exists('@component', $resource)) {
//                    // Passing the variables to the resource
//                    $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
//                }
        }

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/pitches")
     * @Template
     */
    public function pitchesAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/pitches']); // Lets see if there is a post to procces

        // Get resource
        $variables['resources'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'pitches']);

//        var_dump($variables['user']['@id']);
//        die;
//
//        var_dump($variables['resources']);
        //die;

        if ($request->isMethod('POST')) {
            if (isset($_POST['filterPitches'])) {
                $parameters = $request->request->all();

                $date = $parameters['dateSubmitted'];

                // Because you cant filter for 1 date we have to filter between 2 dates
                $date1 = date('Y-m-d', strtotime($date.' - 1 day'));
                $date2 = date('Y-m-d', strtotime($date.' + 1 day'));

                $variables['resources'] = [];
                $variables['resources'] = $commonGroundService->getResourceList(['component'=>'chrc', 'type'=>'pitches'], ['name'=>$parameters['name'], 'description'=>$parameters['keywords'], 'requiredBudget[between]'=> $parameters['minBudget'].'..'.$parameters['maxBudget'], 'created[strictly_after]'=>$date1, 'created[strictly_before]'=>$date2]);

                unset($parameters);

                if (empty($variables['resources']['hydra:member'])) {
                    unset($variables['resources']);
                }
            }
        }

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/pitches/{id}")
     * @Template
     */
    public function pitchAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params, $id)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        if ($params->get('app_id') == 'be1fd311-525b-4408-beb1-012d27af1ff3') { //stage app
            $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/oplossing']);
        } else {
            $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/pitch']);
        }

        // Get resource
        $variables['resource'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'pitches', 'id' => $id]);

//        var_dump($variables['user']['@id']);
//        die;

        if ($request->isMethod('POST')) {

            // Make a review/comment
            if (isset($_POST['add_comment'])) {
                $resource['author'] = $variables['user']['@id'];
                $resource['resource'] = $variables['resource']['@id'];
                $resource['review'] = $request->request->get('review');
                $resource['organization'] = 'https://wrc.dev.zuid-drecht.nl/organizations/4d1eded3-fbdf-438f-9536-8747dd8ab591';

                $resource = $commonGroundService->createResource($resource, ['component' => 'rc', 'type' => 'reviews']);
            } else {
                $resource = $request->request->all();

                if (array_key_exists('@component', $resource)) {
                    // Passing the variables to the resource
                    $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
                }
            }
        }

        // Get all reviews/comments of this resource
        $variables['comments'] = $commonGroundService->getResourceList(['component' => 'rc', 'type' => 'reviews'], ['resource' => $variables['resource']['@id']]);

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/challenges/{id}")
     * @Template
     */
    public function challengeAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params, $id)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/challenge']);
        $variables['resource'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders', 'id' => $id]);

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            if (array_key_exists('@component', $resource)) {
                // Passing the variables to the resource
                $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
            }
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/challenges")
     * @Template
     */
    public function challengesAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/challenges']);

        // Get resource
        $variables['resources'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'tenders']);

//        var_dump($variables['resources']);

        if ($request->isMethod('POST')) {
            if (isset($_POST['filter'])) {
                $parameters = $request->request->all();

                $date = $parameters['dateSubmitted'];

                // Because you cant filter for 1 date we have to filter between 2 dates
                $date1 = date('Y-m-d', strtotime($date.' - 1 day'));
                $date2 = date('Y-m-d', strtotime($date.' + 1 day'));

                $variables['resources'] = [];
                $variables['resources'] = $commonGroundService->getResourceList(['component'=>'chrc', 'type'=>'tenders'], ['name'=>$parameters['name'], 'description'=>$parameters['keywords'], 'budget[between]'=> $parameters['minBudget'].'..'.$parameters['maxBudget'], 'created[strictly_after]'=>$date1, 'created[strictly_before]'=>$date2]);

                unset($parameters);

                if (empty($variables['resources']['hydra:member'])) {
                    unset($variables['resources']);
                }
            }
        }

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/proposals/{id}")
     * @Template
     */
    public function proposalAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params, $id)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/proposal']);
        $variables['resource'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'proposals', 'id' => $id]);

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            if (array_key_exists('@component', $resource)) {
                // Passing the variables to the resource
                $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
            }
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/deals/{id}")
     * @Template
     */
    public function dealAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params, $id)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/deal']);
        $variables['resource'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'deals', 'id' => $id]);

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            if (array_key_exists('@component', $resource)) {
                // Passing the variables to the resource
                $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
            }
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }

    /**
     * @Route("/questions/{id}")
     * @Template
     */
    public function questionAction(Session $session, Request $request, ApplicationService $applicationService, CommonGroundService $commonGroundService, ParameterBagInterface $params, $id)
    {
        $content = false;
        $variables = $applicationService->getVariables();

        // Lets provide this data to the template
        $variables['query'] = $request->query->all();
        $variables['post'] = $request->request->all();

        // Lets find an appoptiate slug
        $template = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id').'/question']);
        $variables['resource'] = $commonGroundService->getResource(['component' => 'chrc', 'type' => 'questions', 'id' => $id]);

        if ($template && array_key_exists('content', $template)) {
            $content = $template['content'];
        }

        // Lets see if there is a post to procces
        if ($request->isMethod('POST')) {
            $resource = $request->request->all();
            if (array_key_exists('@component', $resource)) {
                // Passing the variables to the resource
                $configuration = $commonGroundService->saveResource($resource, ['component' => $resource['@component'], 'type' => $resource['@type']]);
            }
        }

        // Create the template
        if ($content) {
            $template = $this->get('twig')->createTemplate($content);
            $template = $template->render($variables);
        } else {
            $template = $this->render('404.html.twig', $variables);

            return $template;
        }

        return $response = new Response(
            $template,
            Response::HTTP_OK,
            ['content-type' => 'text/html']
        );
    }
}
