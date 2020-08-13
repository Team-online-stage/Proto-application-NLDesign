<?php

// src/Controller/DashboardController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DashboardController.
 *
 * @Route("/drc")
 */
class DrcController extends AbstractController
{
    /**
     * @Route("/download/{resource}")
     * @Template
     */
    public function downloadAction(Request $request, EntityManagerInterface $em, CommonGroundService $commonGroundService, $resource)
    {
        $resource = urldecode($resource);
        $token = $commonGroundService->getJwtToken('drc');
        $commonGroundService->setHeader('Authorization', 'Bearer '.$token);
        $commonGroundService->setHeader('Accept', '*/*');

        $result = $commonGroundService->getResource($resource);
        $commonGroundService->setHeader('Authorization', $this->getParameter('app_commonground_key'));
        $commonGroundService->setHeader('Accept', 'application/ld+json');
//        var_dump($result);

        $headers = ['Authorization'=>'Bearer '.$token];
        $guzzleConfig = [
            // Base URI is used with relative requests
            'http_errors' => false,
            //'base_uri' => 'https://wrc.zaakonline.nl/applications/536bfb73-63a5-4719-b535-d835607b88b2/',
            // You can set any number of default request options.
            'timeout'  => 4000.0,
            // To work with NLX we need a couple of default headers
            'headers' => $headers,
            // Do not check certificates
            'verify' => false,
        ];

        // Lets start up a default client
        $client = new Client($guzzleConfig);

        $data = $client->get($result['inhoud'])->getBody()->getContents();

        $response = new Response(
            $data,
            Response::HTTP_OK,
            ['content-type'=> $result['formaat'], 'Content-Disposition'=>'attachment; filename='.$result['bestandsnaam']],
        );

        $response->send();
    }
}
