<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notification;

use App\Entity\DeviceToken;
use App\Entity\User;
use App\Enum\DevicePlatform;
use App\Service\Notification\Push\FcmPushSender;
use App\Service\Notification\Push\PushContent;
use App\Service\Notification\Push\PushResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Lot 11 §3.5 — passerelle FCM HTTP v1 sans réseau : forme du message par plateforme, échange
 * OAuth mis en cache, et surtout **classement des erreurs** — seul un refus qui désigne le jeton
 * l'invalide ; une panne, un quota ou un défaut de nos identifiants ne doit jamais faire taire
 * l'appareil d'un utilisateur.
 */
#[CoversClass(FcmPushSender::class)]
final class FcmPushSenderTest extends TestCase
{
    private static string $credentialsFile;

    public static function setUpBeforeClass(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        openssl_pkey_export($key, $pem);
        self::$credentialsFile = (string) tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents(self::$credentialsFile, json_encode([
            'type' => 'service_account',
            'project_id' => 'coparent-test',
            'client_email' => 'push@coparent-test.iam.gserviceaccount.com',
            'private_key' => $pem,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$credentialsFile);
    }

    /**
     * @param list<MockResponse>                                                      $responses
     * @param list<array{method: string, url: string, options: array<string, mixed>}> $calls
     */
    private function sender(array $responses, array &$calls = [], ?string $file = null): FcmPushSender
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$responses, &$calls): MockResponse {
            $calls[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return array_shift($responses) ?? new MockResponse('', ['http_code' => 500]);
        });

        return new FcmPushSender($client, new ArrayAdapter(), new NullLogger(), $file ?? self::$credentialsFile);
    }

    private static function oauth(): MockResponse
    {
        return new MockResponse((string) json_encode(['access_token' => 'ya29.test', 'expires_in' => 3599]));
    }

    private static function fcmError(int $status, string $code, string $message = ''): MockResponse
    {
        return new MockResponse((string) json_encode(['error' => [
            'code' => $status,
            'status' => 404 === $status ? 'NOT_FOUND' : 'INVALID_ARGUMENT',
            'message' => $message,
            'details' => [['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => $code]],
        ]]), ['http_code' => $status]);
    }

    private static function device(DevicePlatform $platform = DevicePlatform::Android): DeviceToken
    {
        return new DeviceToken((new User())->setEmail('fcm@example.com'), $platform, 'jeton-fcm', 'hash');
    }

    private static function content(): PushContent
    {
        return new PushContent('Nouveau message', 'Dans l\'échange « T »', ['eventId' => 'exchange_message:f1', 'familyId' => 'fam'], 'exchange_message:f1');
    }

    public function testDisabledWithoutCredentials(): void
    {
        $calls = [];
        self::assertFalse($this->sender([], $calls, '')->isEnabled());
        self::assertFalse($this->sender([], $calls, '/chemin/inexistant.json')->isEnabled());
        self::assertTrue($this->sender([])->isEnabled());
    }

    public function testDeliversAndReusesAccessToken(): void
    {
        $calls = [];
        $sender = $this->sender([self::oauth(), new MockResponse('{"name":"projects/x/messages/1"}'), new MockResponse('{"name":"projects/x/messages/2"}')], $calls);

        self::assertSame(PushResult::Delivered, $sender->send(self::device(), self::content()));
        self::assertSame(PushResult::Delivered, $sender->send(self::device(), self::content()));

        self::assertCount(3, $calls); // un seul échange OAuth pour deux envois
        self::assertSame('https://oauth2.googleapis.com/token', $calls[0]['url']);
        self::assertStringContainsString('grant_type=urn%3Aietf%3Aparams%3Aoauth%3Agrant-type%3Ajwt-bearer', (string) $calls[0]['options']['body']);
        self::assertSame('https://fcm.googleapis.com/v1/projects/coparent-test/messages:send', $calls[1]['url']);
        self::assertContains('Authorization: Bearer ya29.test', $calls[1]['options']['headers']);
    }

    public function testAssertionIsAVerifiableRs256Jwt(): void
    {
        $calls = [];
        $this->sender([self::oauth(), new MockResponse('{}')], $calls)->send(self::device(), self::content());

        parse_str((string) $calls[0]['options']['body'], $form);
        self::assertIsString($form['assertion'] ?? null);
        [$header, $claims, $signature] = explode('.', $form['assertion']);
        $decode = static fn (string $s): string => (string) base64_decode(strtr($s, '-_', '+/'), true);
        self::assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($decode($header), true));
        $payload = json_decode($decode($claims), true);
        self::assertSame('https://www.googleapis.com/auth/firebase.messaging', $payload['scope']);
        self::assertSame('push@coparent-test.iam.gserviceaccount.com', $payload['iss']);

        $credentials = json_decode((string) file_get_contents(self::$credentialsFile), true);
        $private = openssl_pkey_get_private($credentials['private_key']);
        self::assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        self::assertIsArray($details);
        self::assertSame(1, openssl_verify($header.'.'.$claims, $decode($signature), $details['key'], \OPENSSL_ALGO_SHA256));
    }

    public function testMessageShapeDependsOnPlatform(): void
    {
        $calls = [];
        $sender = $this->sender([self::oauth(), new MockResponse('{}'), new MockResponse('{}')], $calls);
        $sender->send(self::device(DevicePlatform::Android), self::content());
        $sender->send(self::device(DevicePlatform::Ios), self::content());

        $android = json_decode((string) $calls[1]['options']['body'], true)['message'];
        $ios = json_decode((string) $calls[2]['options']['body'], true)['message'];

        self::assertSame('jeton-fcm', $android['token']);
        self::assertSame(['title' => 'Nouveau message', 'body' => 'Dans l\'échange « T »'], $android['notification']);
        self::assertSame(['eventId' => 'exchange_message:f1', 'familyId' => 'fam'], $android['data']);
        self::assertSame('HIGH', $android['android']['priority']);
        self::assertArrayNotHasKey('apns', $android);
        self::assertArrayHasKey('apns-collapse-id', $ios['apns']['headers']);
        self::assertLessThanOrEqual(64, \strlen($ios['apns']['headers']['apns-collapse-id']));
        self::assertSame($android['android']['collapse_key'], $ios['apns']['headers']['apns-collapse-id']);
        self::assertArrayNotHasKey('android', $ios);
    }

    /**
     * @return iterable<string, array{0: MockResponse, 1: PushResult}>
     */
    public static function gatewayAnswers(): iterable
    {
        yield 'jeton désinscrit' => [self::fcmError(404, 'UNREGISTERED'), PushResult::InvalidToken];
        yield 'jeton d\'un autre projet' => [self::fcmError(403, 'SENDER_ID_MISMATCH'), PushResult::InvalidToken];
        yield 'jeton mal formé' => [self::fcmError(400, 'INVALID_ARGUMENT', 'The registration token is not a valid FCM registration token'), PushResult::InvalidToken];
        yield 'message mal formé' => [self::fcmError(400, 'INVALID_ARGUMENT', 'Invalid value at \'message.data[0].value\''), PushResult::Rejected];
        yield 'quota' => [self::fcmError(429, 'QUOTA_EXCEEDED'), PushResult::Retryable];
        yield 'indisponible' => [self::fcmError(503, 'UNAVAILABLE'), PushResult::Retryable];
        yield 'erreur interne' => [self::fcmError(500, 'INTERNAL'), PushResult::Retryable];
        yield 'clé APNs refusée' => [self::fcmError(401, 'THIRD_PARTY_AUTH_ERROR'), PushResult::AuthFailure];
        yield 'jeton OAuth refusé' => [new MockResponse('{"error":{"code":401,"status":"UNAUTHENTICATED"}}', ['http_code' => 401]), PushResult::AuthFailure];
    }

    #[DataProvider('gatewayAnswers')]
    public function testClassifiesGatewayAnswers(MockResponse $answer, PushResult $expected): void
    {
        self::assertSame($expected, $this->sender([self::oauth(), $answer])->send(self::device(), self::content()));
    }

    public function testNetworkFailureIsRetryable(): void
    {
        $failing = new MockResponse('', ['error' => 'Connexion refusée']);

        self::assertSame(PushResult::Retryable, $this->sender([self::oauth(), $failing])->send(self::device(), self::content()));
    }

    public function testRejectedServiceAccountIsAnAuthFailureNotATokenProblem(): void
    {
        $refused = new MockResponse('{"error":"invalid_grant","error_description":"Invalid JWT Signature."}', ['http_code' => 400]);

        self::assertSame(PushResult::AuthFailure, $this->sender([$refused])->send(self::device(), self::content()));
    }

    public function testOauthOutageIsRetryable(): void
    {
        self::assertSame(PushResult::Retryable, $this->sender([new MockResponse('', ['http_code' => 503])])->send(self::device(), self::content()));
    }
}
