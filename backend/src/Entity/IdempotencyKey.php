<?php

declare(strict_types=1);

namespace App\Entity;

use App\Attribute\PersonalData;
use App\Doctrine\Type\EncryptedStringType;
use App\Entity\Concern\TimestampableTrait;
use App\Enum\DataCategory;
use App\Enum\IdempotencyStatus;
use App\Repository\IdempotencyKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Store d'idempotence serveur (cdc §6.1, Lot 8 §3.1/§6.3). Une entrée par mutation `Idempotency-Key`,
 * scopée à `(user, endpoint, clé)` avec empreinte du payload (détection d'incohérence §4.2).
 *
 * La réponse mémorisée est **chiffrée au repos** (elle peut contenir des données personnelles /
 * 🔒 déchiffrées pour le sujet) : conforme à §4.8 (« ne mémorise pas de données 🔒 en clair »).
 * Rétention bornée (TTL) : purgée par le PurgeScheduler (scope `idempotency_keys`).
 */
#[ORM\Entity(repositoryClass: IdempotencyKeyRepository::class)]
#[ORM\Table(name: 'idempotency_keys')]
#[ORM\UniqueConstraint(name: 'uniq_idempotency_user_key', columns: ['user_id', 'idempotency_key'])]
#[ORM\Index(name: 'idx_idempotency_created', columns: ['created_at'])]
#[ORM\HasLifecycleCallbacks]
class IdempotencyKey
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 128)]
    private string $idempotencyKey;

    #[ORM\Column(length: 10)]
    private string $method;

    #[ORM\Column(length: 255)]
    private string $endpoint;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $familyId;

    #[ORM\Column(length: 64)]
    private string $payloadHash;

    #[ORM\Column(length: 16, enumType: IdempotencyStatus::class)]
    private IdempotencyStatus $status = IdempotencyStatus::InProgress;

    #[ORM\Column(nullable: true)]
    private ?int $responseCode = null;

    #[PersonalData(
        category: DataCategory::Content,
        purpose: 'Rejouer à l\'identique la réponse d\'une mutation idempotente (dédoublonnage réseau).',
        legalBasis: 'Exécution du contrat (fiabilité de la synchronisation hors-ligne).',
        retention: 'TTL court (par défaut 48 h) ; purge planifiée RGPD (scope idempotency_keys).',
        recipients: 'Interne (usage serveur) ; jamais exposé.',
        encrypted: true,
    )]
    #[ORM\Column(type: EncryptedStringType::NAME, nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $responseContentType = null;

    public function __construct(
        User $user,
        string $idempotencyKey,
        string $method,
        string $endpoint,
        ?string $familyId,
        string $payloadHash,
    ) {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->idempotencyKey = $idempotencyKey;
        $this->method = $method;
        $this->endpoint = $endpoint;
        $this->familyId = $familyId;
        $this->payloadHash = $payloadHash;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getFamilyId(): ?string
    {
        return $this->familyId;
    }

    public function getPayloadHash(): string
    {
        return $this->payloadHash;
    }

    public function getStatus(): IdempotencyStatus
    {
        return $this->status;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function getResponseContentType(): ?string
    {
        return $this->responseContentType;
    }

    /** Marque la mutation terminée et mémorise la réponse (corps chiffré au repos). */
    public function complete(int $responseCode, string $responseBody, ?string $contentType): static
    {
        $this->status = IdempotencyStatus::Completed;
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
        $this->responseContentType = $contentType;

        return $this;
    }

    /** Cette entrée correspond-elle EXACTEMENT à la même requête (scope + payload) ? (§4.2) */
    public function matches(string $method, string $endpoint, ?string $familyId, string $payloadHash): bool
    {
        return $this->method === $method
            && $this->endpoint === $endpoint
            && $this->familyId === $familyId
            && $this->payloadHash === $payloadHash;
    }
}
