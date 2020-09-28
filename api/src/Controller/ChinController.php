<?php

// src/Controller/ProcessController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Security\User\CommongroundUser;
use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use function GuzzleHttp\Promise\all;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * The Procces test handles any calls that have not been picked up by another test, and wel try to handle the slug based against the wrc.
 *
 * Class ProcessController
 *
 * @Route("/chin")
 */
class ChinController extends AbstractController
{
    /**
     * @Route("/checkin/user")
     * @Template
     */
    public function checkinUserAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/checkin/organisation")
     * @Template
     */
    public function checkinOrganizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['person' => $this->getUser()->getOrganization(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/checkin/statistics")
     * @Template
     */
    public function checkinStatisticsAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['checkins'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'checkins'], ['person' => $this->getUser()->getOrganization(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/nodes/user")
     * @Template
     */
    public function nodesUserAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['nodes'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['person' => $this->getUser()->getPerson(), 'order[dateCreated]' => 'desc'])['hydra:member'];

        return $variables;
    }

    /**
     * @Route("/nodes/organization")
     * @Template
     */
    public function nodesOrganizationAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['organizations'] = $commonGroundService->getResource($this->getUser()->getOrganization());
        $variables['places'] = $commonGroundService->getResourceList(['component' => 'lc', 'type' => 'places'], ['organization' => $variables['organizations']['@id']])['hydra:member'];
        $variables['nodes'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['organization' => $variables['organizations']['@id']])['hydra:member'];

        if ($request->isMethod('POST')) {
            $resource = $request->request->all();

            $commonGroundService->saveResource($resource, (['component' => 'chin', 'type' => 'nodes']));

            return $this->redirect($this->generateUrl('app_chin_nodesorganization'));
        }

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/")
     * @Template
     */
    public function indexAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        $variables = $applicationService->getVariables();
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'cmc', 'type' => 'contact_moments'], ['receiver' => $this->getUser()->getPerson()])['hydra:member'];

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/checkin/{code}")
     * @Template
     */
    public function checkinAction(Session $session, $code = null, Request $request, FlashBagInterface $flash, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];
        $session->set('code', $code);
        $variables['code'] = $code;

        // Oke we want a user so lets check if we have one
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_chin_login', ['code'=>$code]));
        }

        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        // We want this resource to be a checkin
        if ($variables['resource']['type'] != 'checkin') {
            switch ($variables['resource']['type']) {
                case 'reservation':
                    return $this->redirect($this->generateUrl('app_chin_reservation', ['code'=>$code]));
                    break;
                default:
                    $this->addFlash('warning', 'Could not find a valid type for reference '.$code);

                    return $this->redirect($this->generateUrl('app_default_index'));
            }
        }

        $variables['code'] = $code;

        if ($request->isMethod('POST') && $request->request->get('method') == 'checkin') {

            //update person
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $tel = $request->request->get('telephone');

            $person = $commonGroundService->getResource($this->getUser()->getPerson());

            // Wat doet dit?
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['person' => $this->getUser()->getPerson()])['hydra:member'];
            $user = $user[0];

            if (isset($person['emails'][0])) {
                //$emailResource = $person['emails'][0];
                //$emailResource['email'] = $email;
                // @Hotfix
                //$emailResource['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'emails', 'id'=>$emailResource['id']]);
                //$emailResource = $commonGroundService->updateResource($emailResource);
                //$person['emails'][0] = 'emails/'.$emailResource['id'];
            } else {
                $emailObject['email'] = $email;
                $emailObject = $commonGroundService->createResource($emailObject, ['component' => 'cc', 'type' => 'emails']);
                $person['emails'][0] = 'emails/'.$emailObject['id'];
            }

            if (isset($person['telephones'][0])) {
                //$telephoneResource = $person['telephones'][0];
                //$telephoneResource['telephone'] = $tel;
                // @Hotfix
                //$telephoneResource['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'telephones', 'id'=>$telephoneResource['id']]);
                //$telephoneObject = $commonGroundService->updateResource($telephoneResource);
                //$person['telephones'][0] = 'telephones/'.$telephoneObject['id'];
            } elseif ($tel) {
                $telephoneObject['telephone'] = $tel;
                $telephoneObject = $commonGroundService->createResource($telephoneObject, ['component' => 'cc', 'type' => 'telephones']);
                $person['telephones'][0] = 'telephones/'.$telephoneObject['id'];
            }

            // @Hotfix
            $person['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'people', 'id'=>$person['id']]);
            //$person = $commonGroundService->updateResource($person);

            // Create check-in
            $checkIn = [];
            $checkIn['node'] = 'nodes/'.$variables['resource']['id'];
            $checkIn['person'] = $person['@id'];
            $checkIn['userUrl'] = $user['@id'];
            $checkIn['provider'] = $session->get('checkingProvider');

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);

            return $this->redirect($this->generateUrl('app_chin_confirmation', ['code'=>$code]));
        }

        return $variables;
    }

    /**
     * @Route("/edit")
     * @Template
     */
    public function editAction(Session $session, Request $request, CommonGroundService $commonGroundService)
    {
        $variables['code'] = $session->get('code');
        $nodes = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $variables['code']])['hydra:member'];
        $variables['person'] = $commonGroundService->getResource($this->getUser()->getPerson());

        if (count($nodes) > 0) {
            $variables['node'] = $nodes[0];
        }

        if ($request->isMethod('POST')) {
            $person = $variables['person'];

            $firstName = $request->get('firstName');
            $lastName = $request->get('lastName');
            $telephone = $request->get('telephone');
            $email = $request->get('email');

            $person['firstName'] = $firstName;
            $person['familyName'] = $lastName;
            if (isset($telephone)) {
                $person['telephones'][0] = [];
                $person['telephones'][0]['telephone'] = $telephone;
            }
            if (isset($email)) {
                $person['emails'][0] = [];
                $person['emails'][0]['email'] = $email;
            }

            $person = $commonGroundService->updateResource($person);

            $backUrl = $session->get('backUrl', false);
            if ($backUrl) {
                return $this->redirect($backUrl);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        }

        return $variables;
    }

    /**
     * @Route("/reset/{token}")
     * @Template
     */
    public function resetAction(Session $session, Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $params, $token = null)
    {
        $variables['code'] = $session->get('code');
        $nodes = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $variables['code']])['hydra:member'];

        if ($token !== null) {
            $application = $commonGroundService->getResource(['component'=>'wrc', 'type'=>'applications', 'id' => $params->get('app_id')]);
            $providers = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'reset', 'application' => $params->get('app_id')])['hydra:member'];
        }

        if (count($nodes) > 0) {
            $variables['node'] = $nodes[0];
        }

        if ($request->isMethod('POST')) {
            $variables['message'] = true;
            $username = $request->get('email');
            $users = $commonGroundService->getResourceList(['component'=>'uc', 'type'=>'users'], ['username'=> $username], true, false, true, false, false);
            $users = $users['hydra:member'];

            $application = $commonGroundService->getResource(['component'=>'wrc', 'type'=>'applications', 'id' => $params->get('app_id')]);
            $organization = $application['organization']['@id'];
            $providers = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'providers'], ['type' => 'reset', 'application' => $params->get('app_id')])['hydra:member'];

            if (count($users) > 0) {
                $user = $users[0];
                $person = $commonGroundService->getResource($user['person']);

                $validChars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $code = substr(str_shuffle(str_repeat($validChars, ceil(3 / strlen($validChars)))), 1, 5);

                $token = [];
                $token['token'] = $code;
                $token['user'] = 'users/'.$user['id'];
                $token['provider'] = 'providers/'.$providers[0]['id'];
                $token = $commonGroundService->createResource($token, ['component' => 'uc', 'type' => 'tokens']);

                $url = $request->getUri();
                $link = $url.'/'.$token['token'];

                $content = $commonGroundService->getResource(['component'=>'wrc', 'type'=>'applications', 'id'=>"{$params->get('app_id')}/e-mail-reset"])['@id'];

                $message = [];
                $service = $commonGroundService->getResourceList(['component'=>'bs', 'type'=>'services'], 'type=mailer')['hydra:member'][0];

                $message['service'] = '/services/'.$service['id'];
                $message['status'] = 'queued';
                $message['data'] = ['resource'=>$link, 'sender'=>$organization, 'receiver'=>$person['@id']];
                $message['content'] = $content;
                $message['reciever'] = $person['@id'];
                $message['sender'] = $organization;

                $commonGroundService->createResource($message, ['component'=>'bs', 'type'=>'messages']);
            }
        }

        return $variables;
    }

    /**
     * This function will kick of the suplied proces with given values.
     *
     * @Route("/reservation/{code}")
     * @Template
     */
    public function reservationAction(Session $session, $code = null, Request $request, FlashBagInterface $flash, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];
        $session->set('code', $code);
        $variables['code'] = $code;

        // Oke we want a user so lets check if we have one
        if (!$this->getUser()) {
            return $this->redirect($this->generateUrl('app_chin_login', ['code'=>$code]));
        }

        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        // We want this resource to be a checkin
        if ($variables['resource']['type'] != 'reservation') {
            switch ($variables['resource']['type']) {
                case 'checkin':
                    return $this->redirect($this->generateUrl('app_chin_checkin', ['code'=>$code]));
                    break;
                default:
                    $this->addFlash('warning', 'Could not find a valid type for reference '.$code);

                    return $this->redirect($this->generateUrl('app_default_index'));
            }
        }

        $variables['code'] = $code;
        $variables['organization'] = $commonGroundService->getResource($variables['resource']['organization']);
        $variables['nodes'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['organization' =>$variables['organization']['id']])['hydra:member'];

        if ($request->isMethod('POST') && $request->request->get('method') == 'checkin') {

            //update person
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $tel = $request->request->get('telephone');

            $person = $commonGroundService->getResource($this->getUser()->getPerson());

            // Wat doet dit?
            $user = $commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['person' => $this->getUser()->getPerson()])['hydra:member'];
            $user = $user[0];

            if (isset($person['emails'][0])) {
                //$emailResource = $person['emails'][0];
                //$emailResource['email'] = $email;
                // @Hotfix
                //$emailResource['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'emails', 'id'=>$emailResource['id']]);
                //$emailResource = $commonGroundService->updateResource($emailResource);
                //$person['emails'][0] = 'emails/'.$emailResource['id'];
            } else {
                $emailObject['email'] = $email;
                $emailObject = $commonGroundService->createResource($emailObject, ['component' => 'cc', 'type' => 'emails']);
                $person['emails'][0] = 'emails/'.$emailObject['id'];
            }

            if (isset($person['telephones'][0])) {
                //$telephoneResource = $person['telephones'][0];
                //$telephoneResource['telephone'] = $tel;
                // @Hotfix
                //$telephoneResource['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'telephones', 'id'=>$telephoneResource['id']]);
                //$telephoneObject = $commonGroundService->updateResource($telephoneResource);
                //$person['telephones'][0] = 'telephones/'.$telephoneObject['id'];
            } elseif ($tel) {
                $telephoneObject['telephone'] = $tel;
                $telephoneObject = $commonGroundService->createResource($telephoneObject, ['component' => 'cc', 'type' => 'telephones']);
                $person['telephones'][0] = 'telephones/'.$telephoneObject['id'];
            }

            // @Hotfix
            $person['@id'] = $commonGroundService->cleanUrl(['component'=>'cc', 'type'=>'people', 'id'=>$person['id']]);
            //$person = $commonGroundService->updateResource($person);

            // Create check-in
            $reservation = [];
            $reservation['node'] = 'nodes/'.$variables['resource']['id'];
            $reservation['person'] = $person['@id'];
            $reservation['userUrl'] = $user['@id'];

            $checkIn = $commonGroundService->createResource($reservation, ['component' => 'chin', 'type' => 'reservations']);

            return $this->redirect($this->generateUrl('app_chin_confirmation', ['code'=>$code]));
        }

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/login/{code}")
     * @Template
     */
    public function loginAction(Session $session, $code = null, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];

        $session->set('code', $code);
        $variables['code'] = $code;

        // If we have a valid user then we do not need to login
        if ($this->getUser()) {
            $session->set('checkingProvider', 'session');

            return $this->redirect($this->generateUrl('app_chin_checkin', ['code'=>$code]));
        }

        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables['code'] = $code;

        if ($request->isMethod('POST') && $request->request->get('method')) {
            $method = $request->request->get('method');

            switch ($method) {
                case 'idin':
                    return $this->redirect($this->generateUrl('app_user_idin', ['backUrl'=>$this->generateUrl('app_chin_checkin', ['code'=>$code], urlGeneratorInterface::ABSOLUTE_URL)]));
                case 'idinLogin':
                    return $this->redirect($this->generateUrl('app_user_idinlogin', ['backUrl'=>$this->generateUrl('app_chin_checkin', ['code'=>$code], urlGeneratorInterface::ABSOLUTE_URL)]));
                case 'facebook':
                    return $this->redirect($this->generateUrl('app_user_facebook', ['backUrl'=>$this->generateUrl('app_chin_checkin', ['code'=>$code], urlGeneratorInterface::ABSOLUTE_URL)]));
                case 'google':
                    return $this->redirect($this->generateUrl('app_user_gmail', ['backUrl'=>$this->generateUrl('app_chin_checkin', ['code'=>$code], urlGeneratorInterface::ABSOLUTE_URL)]));
                case 'acount':
                    return $this->redirect($this->generateUrl('app_chin_acount', ['code'=>$code]));
            }
        }

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/acount/{code}")
     * @Template
     */
    public function acountAction(Session $session, $code = null, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];

        $session->set('code', $code);
        $variables['code'] = $code;
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables['code'] = $code;

        // Lets handle a post
        if ($request->isMethod('POST')) {
            $name = $request->request->get('name');
            $username = $request->request->get('email');
            $tel = $request->request->get('telephone');
            $password = $request->request->get('password');
            $crf = $request->request->get('_csrf_token');

            $users = $commonGroundService->getResourceList(['component'=>'uc', 'type'=>'users'], ['username'=> $username], true, false, true, false, false);
            $users = $users['hydra:member'];

            // Exsisting user
            if (count($users) > 0) {
                $user = $users[0];
                $person = $commonGroundService->getResource($user['person']);

                $credentials = [
                    'username'   => $username,
                    'password'   => $password,
                    'csrf_token' => $crf,
                ];

                $user = $commonGroundService->createResource($credentials, ['component'=>'uc', 'type'=>'login'], false, true, false, false);

                // validate user
                if (!$user) {
                    $variables['password_error'] = 'invalid password';
                    $variables['user_info']['name'] = $name;
                    $variables['user_info']['email'] = $username;
                    $variables['user_info']['telephone'] = $tel;

                    return $variables;
                }

                // Login the user
                $userObject = new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'user');

                $token = new UsernamePasswordToken($userObject, null, 'main', $userObject->getRoles());
                $this->container->get('security.token_storage')->setToken($token);
                $this->container->get('session')->set('_security_main', serialize($token));
            }
            // Non-Exsisting user
            else {
                //create email
                $email = [];
                $email['name'] = 'Email';
                $email['email'] = $username;
                //$email = $this->commonGroundService->createResource($email, ['component' => 'cc', 'type' => 'emails']);

                $telephone = [];
                $telephone['name'] = 'Phone';
                $telephone['telephone'] = $tel;
                //$email = $this->commonGroundService->createResource($telephone, ['component' => 'cc', 'type' => 'telephones']);

                //create person
                $names = explode(' ', $name);
                $person = [];
                $person['givenName'] = $names[0];
                $person['familyName'] = end($names);
                $person['emails'] = [$email];
                if ($tel) {
                    $person['telephones'] = [$telephone];
                }

                $person = $commonGroundService->createResource($person, ['component' => 'cc', 'type' => 'people']);

                //create user
                $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id')]);
                $user = [];
                $user['username'] = $username;
                $user['password'] = $password;
                $user['person'] = $person['@id'];
                $user['organization'] = $application['organization']['@id'];
                $user = $commonGroundService->createResource($user, ['component' => 'uc', 'type' => 'users']);

                $userObject = new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'user');

                $token = new UsernamePasswordToken($userObject, null, 'main', $userObject->getRoles());
                $this->container->get('security.token_storage')->setToken($token);
                $this->container->get('session')->set('_security_main', serialize($token));
            }

            $checkIn['node'] = 'nodes/'.$variables['resource']['id'];
            $checkIn['person'] = $person['@id'];
            $checkIn['provider'] = 'email';
            $checkIn['userUrl'] = $user['@id'];

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);

            return $this->redirect($this->generateUrl('app_chin_confirmation', ['code'=>$code]));
        }

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/confirmation/{code}")
     * @Template
     */
    public function confirmationAction(Session $session, $code = null, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];

        $session->set('code', $code);
        $variables['code'] = $code;
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        // Lets handle a post
        if ($request->isMethod('POST')) {
        }

        $variables['code'] = $code;

        return $variables;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/authorization/{code}")
     * @Template
     */
    public function authorizationAction(Session $session, $code = null, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];

        $session->set('code', $code);
        $variables['code'] = $code;
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];

        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        if ($request->isMethod('POST')) {
            $node = $request->request->get('node');
            $name = $request->request->get('name');

            $email = $request->request->get('email');
            $tel = $request->request->get('telephone');
            $name = explode(' ', $name);

            if (count($name) < 2) {
                $firstName = $name[0];
                $additionalName = '';
                $lastName = $name[0];
            } elseif (count($name) < 3) {
                $firstName = $name[0];
                $additionalName = '';
                $lastName = $name[1];
            } else {
                $firstName = $name[0];
                $additionalName = $name[1];
                $lastName = $name[2];
            }

            $emailObject['email'] = $email;
            $emailObject = $commonGroundService->createResource($emailObject, ['component' => 'cc', 'type' => 'emails']);

            $telObject['telephone'] = $tel;
            $telObject = $commonGroundService->createResource($telObject, ['component' => 'cc', 'type' => 'telephones']);

            $person['givenName'] = $firstName;
            $person['additionalName'] = $additionalName;
            $person['familyName'] = $lastName;
            $person['emails'][0] = $emailObject['@id'];
            $person['telephones'][0] = $telObject['@id'];
            $person = $commonGroundService->createResource($person, ['component' => 'cc', 'type' => 'people']);

            $application = $commonGroundService->getResource(['component' => 'wrc', 'type' => 'applications', 'id' => $params->get('app_id')]);
            $validChars = '0123456789abcdefghijklmnopqrstuvwxyz';
            $password = substr(str_shuffle(str_repeat($validChars, ceil(3 / strlen($validChars)))), 1, 8);
            $user = [];
            $user['username'] = $email;
            $user['password'] = $password;
            $user['person'] = $person['@id'];
            $user['organization'] = $application['organization']['@id'];

            $user = $commonGroundService->createResource($user, ['component' => 'uc', 'type' => 'users']);

            $checkIn['node'] = $node;
            $checkIn['person'] = $person['@id'];

            $checkIn = $commonGroundService->createResource($checkIn, ['component' => 'chin', 'type' => 'checkins']);

            $node = $commonGroundService->getResource($node);

            $session->set('newcheckin', true);
            $session->set('person', $person);

            $test = new CommongroundUser($user['username'], $password, $person['name'], null, $user['roles'], $user['person'], null, 'user');

            $token = new UsernamePasswordToken($test, null, 'main', $test->getRoles());
            $this->container->get('security.token_storage')->setToken($token);
            $this->container->get('session')->set('_security_main', serialize($token));

            if (isset($application['defaultConfiguration']['configuration']['userPage'])) {
                return $this->redirect('/'.$application['defaultConfiguration']['configuration']['userPage']);
            } else {
                return $this->redirect($this->generateUrl('app_default_index'));
            }
        }

        $variables['code'] = $code;
    }

    /**
     * This function shows all available locations.
     *
     * @Route("/checkout/{code}")
     * @Template
     */
    public function checkoutAction(Session $session, $code = null, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params)
    {
        // Fallback options of establishing
        if (!$code) {
            $code = $request->query->get('code');
        }
        if (!$code) {
            $code = $request->request->get('code');
        }
        if (!$code) {
            $code = $session->get('code');
        }
        if (!$code) {
            $this->addFlash('warning', 'No node reference suplied');

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables = [];

        $session->set('code', $code);
        $variables['code'] = $code;
        $variables['resources'] = $commonGroundService->getResourceList(['component' => 'chin', 'type' => 'nodes'], ['reference' => $code])['hydra:member'];
        if (count($variables['resources']) > 0) {
            $variables['resource'] = $variables['resources'][0];
        } else {
            $this->addFlash('warning', 'Could not find a valid node for reference '.$code);

            return $this->redirect($this->generateUrl('app_default_index'));
        }

        $variables['code'] = $code;

        return $variables;
    }

    /**
     * @Route("/nodes")
     * @Template
     */
    public function nodesAction(Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $variables = [];
        $variables['nodes'] = $commonGroundService->getResourceList(['component'=>'chin', 'type'=>'nodes'], ['organization'=>$this->getUser()->getOrganization()])['hydra:member'];

        return $variables;
    }
}
