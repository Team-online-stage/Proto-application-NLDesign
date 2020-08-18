<?php

// src/Controller/OrcController.php

namespace App\Controller;

use Conduction\CommonGroundBundle\Service\ApplicationService;
//use App\Service\RequestService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
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
 * @Route("/download")
 */
class DownloadController extends AbstractController
{
    /**
     * @Route("/order/{id}")
     * @Template
     */
    public function orderAction($id, Session $session, Request $request, CommonGroundService $commonGroundService, ApplicationService $applicationService, ParameterBagInterface $params, string $slug = 'home')
    {
        $order = $commonGroundService->getResource(['component' => 'orc', 'type' => 'orders', 'id' => $id]);
        $orderTemplate = $commonGroundService->getResourceList(['component' => 'wrc', 'type' => 'templates'], ['name' => 'Order Template'])['hydra:member'];
        $query = ['resource' => $order['@id']];
        $render = $commonGroundService->createResource($query, $orderTemplate['uri'].'/render');
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        \PhpOffice\PhpWord\Shared\Html::addHtml($section, $render['content']);
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $filename = dirname(__FILE__, 3)."/var/{$order['reference']}.docx";
        $objWriter->save($filename);
        header('Content-Type: application/vnd.ms-word');
        header('Content-Disposition: attachment; filename='.$order['reference'].'.docx');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        flush();
        readfile($filename);
        unlink($filename); // deletes the temporary file
        exit;
    }

}
