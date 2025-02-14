<?php

namespace App\Controller;

use App\Document\Provider;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/{_locale}', requirements: ['_locale' => '%app.locales%'])]
class IndexController extends AbstractController
{
    private array $locales;
    private TranslatorInterface $translator;
    private DocumentManager $documentManager;

    public function __construct(ParameterBagInterface $params, TranslatorInterface $translator, DocumentManager $documentManager)
    {
        $this->locales = explode('|', $params->get('app.locales'));
        $this->translator = $translator;
        $this->documentManager = $documentManager;
    }

    private function getBasicData(string $currentPage, Request $request): array
    {
        $translatedRoutes = array_map(function ($locale) use ($currentPage, $request) {
            return [
                'locale' => $locale,
                'route' => $this->generateUrl($currentPage, ['_locale' => $locale]),
                'active' => $locale === $request->getLocale(),
            ];
        }, $this->locales);

        $providers = $this->documentManager->getRepository(Provider::class)->findAll();

        return [
            'current_page' => $currentPage,
            'translated_routes' => $translatedRoutes,
            'provider_name' => $this->translator->trans('choose_provider'),
            'providers' => $providers,
        ];
    }

    #[Route('/', name: 'home')]
    public function homeWithLocale(Request $request): Response
    {
        return $this->render("home.{$request->getLocale()}.html.twig", $this->getBasicData('home', $request));
    }

    #[Route('/manual', name: 'manual')]
    public function manual(Request $request): Response
    {
        return $this->render("manual.{$request->getLocale()}.html.twig", $this->getBasicData('manual', $request));
    }

    #[Route('/open_data', name: 'open_data')]
    public function openData(Request $request): Response
    {
        return $this->render("open_data.{$request->getLocale()}.html.twig", $this->getBasicData('open_data', $request));
    }

    #[Route('/open_source', name: 'open_source')]
    public function openSource(Request $request): Response
    {
        return $this->render("open_source.{$request->getLocale()}.html.twig", $this->getBasicData('open_source', $request));
    }

    #[Route('/legal', name: 'legal')]
    public function legal(Request $request): Response
    {
        return $this->render("legal.{$request->getLocale()}.html.twig", $this->getBasicData('legal', $request));
    }

    #[Route('/500', name: '500')]
    public function error500(Request $request): Response
    {
        return $this->render("error.html.twig", $this->getBasicData('500', $request));
    }

    #[Route('/403', name: '403')]
    public function error403(Request $request): Response
    {
        return $this->render("error403.html.twig", $this->getBasicData('403', $request));
    }

    #[Route('/404', name: '404')]
    public function error404(Request $request): Response
    {
        return $this->render("error404.html.twig", $this->getBasicData('404', $request));
    }
}
