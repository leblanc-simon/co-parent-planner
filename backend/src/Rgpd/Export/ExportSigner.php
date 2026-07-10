<?php

declare(strict_types=1);

namespace App\Rgpd\Export;

use App\Entity\RgpdOperation;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fabrique et vérifie le **lien signé temporaire** de téléchargement d'un export (§3.5).
 *
 * Signature HMAC (clé applicative) + expiration courte portées dans l'URL par {@see UriSigner} ;
 * un lien altéré ou expiré échoue à la vérification (410/403). Le contrôleur de téléchargement
 * exige EN PLUS que l'utilisateur authentifié soit le sujet (scope au sujet, jamais d'export d'autrui).
 */
final class ExportSigner
{
    public function __construct(
        private readonly UriSigner $uriSigner,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClockInterface $clock,
        #[Autowire('%env(int:RGPD_EXPORT_LINK_TTL)%')]
        private readonly int $linkTtl,
    ) {
    }

    /** Clé de stockage stable de l'artefact d'une opération d'export. */
    public function storageKey(RgpdOperation $operation): string
    {
        return 'export-'.$operation->getId()->toRfc4122().'.zip';
    }

    /** URL absolue signée, à expiration courte, vers le téléchargement de l'export. */
    public function signedUrl(RgpdOperation $operation): string
    {
        $url = $this->urlGenerator->generate(
            'api_me_export_download',
            ['id' => (string) $operation->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $this->uriSigner->sign($url, $this->clock->now()->add(new \DateInterval('PT'.$this->linkTtl.'S')));
    }

    /** Vérifie la signature + l'expiration du lien de la requête entrante. */
    public function isRequestValid(Request $request): bool
    {
        return $this->uriSigner->checkRequest($request);
    }
}
