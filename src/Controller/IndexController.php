<?php

namespace App\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class IndexController extends AbstractController
{
    private DocumentManager $documentManager;
    private TranslatorInterface $translator;

    public function __construct(DocumentManager $documentManager, TranslatorInterface $translator)
    {
        $this->documentManager = $documentManager;
        $this->translator = $translator;
    }

    private function getBasicData(string $currentPage, Request $request): array
    {
        $translatedRoutes = [];

        foreach (explode('|', $this->getParameter('app.locales')) as $locale) {
            $translatedRoutes[] = [
                'locale' => $locale,
                'route' => $this->generateUrl($currentPage, ['_locale' => $locale]),
                'active' => $locale === $request->getLocale(),
            ];
        }

        $providers = $this->documentManager->getRepository(\App\Document\Provider::class)->findAll();

        return [
            'current_page' => $currentPage,
            'translated_routes' => $translatedRoutes,
            'provider_name' => $this->translator->trans('choose_provider'),
            'providers' => $providers,
        ];
    }

    #[Route('/', name: 'home_default')]
    #[Route('/{_locale}', name: 'home', requirements: ['_locale' => '%app.locales%'])]
    public function homeWithLocale(Request $request): Response
    {
        return $this->render("home.{$request->getLocale()}.html.twig", $this->getBasicData('home', $request));
    }

    #[Route('/{_locale}/manual', name: 'manual', requirements: ['_locale' => '%app.locales%'])]
    public function manual(Request $request): Response
    {
        return $this->render("manual.{$request->getLocale()}.html.twig", $this->getBasicData('manual', $request));
    }

    #[Route('/{_locale}/open_data', name: 'open_data', requirements: ['_locale' => '%app.locales%'])]
    public function openData(Request $request): Response
    {
        return $this->render("open_data.{$request->getLocale()}.html.twig", $this->getBasicData('open_data', $request));
    }

    #[Route('/{_locale}/open_source', name: 'open_source', requirements: ['_locale' => '%app.locales%'])]
    public function openSource(Request $request): Response
    {
        return $this->render("open_source.{$request->getLocale()}.html.twig", $this->getBasicData('open_source', $request));
    }

    #[Route('/{_locale}/legal', name: 'legal', requirements: ['_locale' => '%app.locales%'])]
    public function legal(Request $request): Response
    {
        return $this->render("legal.{$request->getLocale()}.html.twig", $this->getBasicData('legal', $request));
    }

    #[Route('/{_locale}/500', name: '500', requirements: ['_locale' => '%app.locales%'])]
    public function error500(Request $request): Response
    {
        return $this->render("error.html.twig", $this->getBasicData('500', $request));
    }

    #[Route('/{_locale}/403', name: '403', requirements: ['_locale' => '%app.locales%'])]
    public function error403(Request $request): Response
    {
        return $this->render("error403.html.twig", $this->getBasicData('403', $request));
    }

    #[Route('/{_locale}/404', name: '404', requirements: ['_locale' => '%app.locales%'])]
    public function error404(Request $request): Response
    {
        return $this->render("error404.html.twig", $this->getBasicData('404', $request));
    }
}
