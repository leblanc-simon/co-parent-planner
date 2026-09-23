<?php

declare(strict_types=1);

namespace App\Service\Notification\Push;

use App\Entity\DeviceToken;
use App\Enum\DevicePlatform;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Envoi par Firebase Cloud Messaging, API HTTP v1 (Lot 11 §3.5).
 *
 * **Une seule passerelle pour les deux plateformes** (arbitrage du 2026-09-23) : FCM relaie vers
 * APNs les jetons iOS, à condition que la clé APNs (.p8) soit déposée dans la console Firebase. Le
 * backend ne détient donc qu'un identifiant : le fichier JSON d'un compte de service Google, dont le
 * chemin vient de `FCM_CREDENTIALS_FILE` — hors dépôt, hors base. Vide, le canal push est éteint.
 *
 * Authentification OAuth 2 « JWT bearer » : une assertion signée RS256 par la clé du compte de
 * service s'échange contre un jeton d'accès d'une heure, mis en cache jusqu'à peu avant son terme.
 *
 * Classement des erreurs (réf. `FcmError`) : `UNREGISTERED` et `SENDER_ID_MISMATCH` condamnent le
 * jeton ; `INVALID_ARGUMENT` seulement quand la passerelle désigne le jeton, sinon c'est notre
 * message qui est en cause et invalider le jeton ferait taire l'appareil pour un défaut du serveur.
 */
final class FcmPushSender implements PushSender
{
    private const string SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const string SEND_URL = 'https://fcm.googleapis.com/v1/projects/%s/messages:send';
    private const string TOKEN_CACHE_KEY = 'fcm_access_token';

    /** @var array{project_id: string, client_email: string, private_key: string, token_uri: string}|false|null */
    private array|false|null $credentials = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::FCM_CREDENTIALS_FILE)%')]
        private readonly ?string $credentialsFile = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return false !== $this->credentials();
    }

    public function send(DeviceToken $device, PushContent $content): PushResult
    {
        $credentials = $this->credentials();
        if (false === $credentials) {
            return PushResult::Rejected;
        }

        try {
            $response = $this->httpClient->request('POST', \sprintf(self::SEND_URL, $credentials['project_id']), [
                'auth_bearer' => $this->accessToken($credentials),
                'json' => ['message' => $this->message($device, $content)],
                'timeout' => 10,
            ]);
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return PushResult::Delivered;
            }
            $error = $response->toArray(false)['error'] ?? [];
        } catch (HttpException|\JsonException|\RuntimeException $e) {
            if ($e instanceof PushDeliveryException) {
                $this->logger->error('Jeton d\'accès FCM refusé.', ['exception' => $e]);

                return PushResult::AuthFailure;
            }
            $this->logger->warning('FCM injoignable.', ['exception' => $e]);

            return PushResult::Retryable;
        }

        return $this->classify($status, \is_array($error) ? $error : []);
    }

    /**
     * Message FCM v1. `collapseKey` regroupe côté système les notifications d'un même événement
     * (`apns-collapse-id` limité à 64 octets, d'où l'empreinte).
     *
     * @return array<string, mixed>
     */
    private function message(DeviceToken $device, PushContent $content): array
    {
        $collapse = substr(hash('sha256', $content->collapseKey), 0, 32);
        $message = [
            'token' => $device->getToken(),
            'notification' => ['title' => $content->title, 'body' => $content->body],
            'data' => $content->data,
        ];

        return $message + match ($device->getPlatform()) {
            DevicePlatform::Android => [
                'android' => ['collapse_key' => $collapse, 'priority' => 'HIGH', 'notification' => ['tag' => $collapse]],
            ],
            DevicePlatform::Ios => [
                'apns' => [
                    'headers' => ['apns-collapse-id' => $collapse, 'apns-priority' => '10'],
                    'payload' => ['aps' => ['sound' => 'default']],
                ],
            ],
        };
    }

    /**
     * @param array<mixed> $error
     */
    private function classify(int $status, array $error): PushResult
    {
        $code = null;
        foreach (\is_array($error['details'] ?? null) ? $error['details'] : [] as $detail) {
            if (\is_array($detail) && isset($detail['errorCode']) && \is_string($detail['errorCode'])) {
                $code = $detail['errorCode'];
            }
        }
        $code ??= \is_string($error['status'] ?? null) ? $error['status'] : null;
        $message = \is_string($error['message'] ?? null) ? $error['message'] : '';

        $result = match (true) {
            'UNREGISTERED' === $code, 'SENDER_ID_MISMATCH' === $code => PushResult::InvalidToken,
            'INVALID_ARGUMENT' === $code && str_contains(strtolower($message), 'registration token') => PushResult::InvalidToken,
            'THIRD_PARTY_AUTH_ERROR' === $code, 401 === $status, 403 === $status => PushResult::AuthFailure,
            429 === $status, $status >= 500 => PushResult::Retryable,
            default => PushResult::Rejected,
        };

        if (PushResult::AuthFailure === $result) {
            // Un jeton d'accès révoqué côté Google ne doit pas survivre en cache jusqu'à son terme.
            $this->cache->delete(self::TOKEN_CACHE_KEY);
            $this->logger->error('FCM refuse les identifiants du serveur.', ['status' => $status, 'code' => $code]);
        } elseif (PushResult::Rejected === $result) {
            $this->logger->error('FCM refuse le message.', ['status' => $status, 'code' => $code, 'message' => $message]);
        }

        return $result;
    }

    /**
     * @param array{project_id: string, client_email: string, private_key: string, token_uri: string} $credentials
     */
    private function accessToken(array $credentials): string
    {
        return $this->cache->get(self::TOKEN_CACHE_KEY, function (ItemInterface $item) use ($credentials): string {
            $now = time();
            $assertion = $this->signedAssertion($credentials, $now);
            $exchange = $this->httpClient->request('POST', $credentials['token_uri'], [
                'body' => [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ],
                'timeout' => 10,
            ]);
            if ($exchange->getStatusCode() >= 500) {
                // Indisponibilité du serveur OAuth : passagère, à ne pas confondre avec un refus.
                throw new \RuntimeException('Serveur OAuth Google indisponible.');
            }
            $response = $exchange->toArray(false);
            $token = $response['access_token'] ?? null;
            if (!\is_string($token) || '' === $token) {
                throw new PushDeliveryException('Réponse OAuth sans access_token.');
            }
            $ttl = \is_int($response['expires_in'] ?? null) ? $response['expires_in'] : 3600;
            $item->expiresAfter(max(60, $ttl - 300));

            return $token;
        });
    }

    /**
     * @param array{project_id: string, client_email: string, private_key: string, token_uri: string} $credentials
     */
    private function signedAssertion(array $credentials, int $now): string
    {
        $segments = [
            self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            self::base64Url((string) json_encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ])),
        ];
        $signature = '';
        if (!openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], \OPENSSL_ALGO_SHA256)) {
            throw new PushDeliveryException('Signature de l\'assertion OAuth impossible (clé du compte de service illisible).');
        }
        $segments[] = self::base64Url($signature);

        return implode('.', $segments);
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * Lecture paresseuse du compte de service : un fichier absent ou incomplet éteint le canal au
     * lieu de faire tomber le conteneur de services (le push est optionnel, l'e-mail ne l'est pas).
     *
     * @return array{project_id: string, client_email: string, private_key: string, token_uri: string}|false
     */
    private function credentials(): array|false
    {
        if (null !== $this->credentials) {
            return $this->credentials;
        }
        if (null === $this->credentialsFile || '' === $this->credentialsFile) {
            return $this->credentials = false;
        }

        $raw = is_readable($this->credentialsFile) ? file_get_contents($this->credentialsFile) : false;
        $json = false !== $raw ? json_decode($raw, true) : null;
        if (!\is_array($json)
            || !\is_string($json['project_id'] ?? null)
            || !\is_string($json['client_email'] ?? null)
            || !\is_string($json['private_key'] ?? null)) {
            $this->logger->error('FCM_CREDENTIALS_FILE illisible ou incomplet : canal push éteint.');

            return $this->credentials = false;
        }

        return $this->credentials = [
            'project_id' => $json['project_id'],
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => \is_string($json['token_uri'] ?? null) ? $json['token_uri'] : 'https://oauth2.googleapis.com/token',
        ];
    }
}
