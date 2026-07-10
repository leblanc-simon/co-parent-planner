<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PWA (Lot 8 §2.1/§3.7) : manifest localisé, service worker et page hors-ligne, tous servis à la
 * **racine du scope** (`/`) — jamais sous `/assets/` (le SW doit contrôler tout le site). L'offline
 * web est **dégradé** (lecture seule) : aucune file d'écriture (décision verrouillée cdc §4.3).
 */
final class PwaController extends AbstractController
{
    /** Logiques d'assets pré-cachés pour l'offline (shell essentiel). */
    private const array PRECACHE_ASSETS = [
        'app.js',
        'styles/app.css',
        'fonts/BricolageGrotesque.ttf',
        'fonts/HankenGrotesk.ttf',
        'icons/icon-192.png',
        'icons/icon-512.png',
        'icons/apple-touch-icon.png',
    ];

    #[Route('/manifest.webmanifest', name: 'app_manifest', methods: ['GET'])]
    public function manifest(): Response
    {
        $response = $this->render('pwa/manifest.webmanifest.twig');
        $response->headers->set('Content-Type', 'application/manifest+json');
        $response->setPublic()->setMaxAge(3600);

        return $response;
    }

    #[Route('/sw.js', name: 'app_service_worker', methods: ['GET'])]
    public function serviceWorker(AssetMapperInterface $assetMapper): Response
    {
        $precache = [];
        foreach (self::PRECACHE_ASSETS as $logicalPath) {
            $public = $assetMapper->getPublicPath($logicalPath);
            if (null !== $public) {
                $precache[] = $public;
            }
        }
        $precache[] = $this->generateUrl('app_offline');

        $response = $this->render('pwa/sw.js.twig', [
            'precache' => $precache,
            // Version = empreinte des URLs digérées : change dès qu'un asset change → purge des anciens caches.
            'version' => substr(sha1(implode('|', $precache)), 0, 12),
        ]);
        $response->headers->set('Content-Type', 'application/javascript');
        // Le SW doit pouvoir piloter tout le scope racine même servi depuis /sw.js.
        $response->headers->set('Service-Worker-Allowed', '/');
        // Ne jamais mettre le SW en cache long (sinon les mises à jour ne se propagent pas).
        $response->headers->set('Cache-Control', 'no-cache');

        return $response;
    }

    #[Route('/offline', name: 'app_offline', methods: ['GET'])]
    public function offline(): Response
    {
        return $this->render('pwa/offline.html.twig');
    }
}
