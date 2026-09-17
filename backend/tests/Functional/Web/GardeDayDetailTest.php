<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Child;
use App\Entity\CustodyColorPreference;
use App\Entity\CustodyDay;
use App\Entity\CustodyException;
use App\Entity\CustodyPattern;
use App\Entity\Event;
use App\Entity\Family;
use App\Entity\HolidayRule;
use App\Entity\Membership;
use App\Entity\Right;
use App\Entity\SchoolHoliday;
use App\Entity\User;
use App\Enum\CustodyDaySource;
use App\Enum\CustodyPalette;
use App\Enum\CustodyPatternKind;
use App\Enum\EventCategory;
use App\Enum\FamilyType;
use App\Enum\HolidayRuleKind;
use App\Enum\HolidaySource;
use App\Enum\MembershipRole;
use App\Enum\MembershipStatus;
use App\Enum\RightDomain;
use App\Enum\RightLevel;
use App\Enum\UserStatus;
use App\Service\Custody\DaySegment;
use App\Service\Custody\DaySegments;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Clock;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Écart de périmètre 6.1 de `specs/revue-web-desktop-2026-07.md` : `/garde` n'avait **ni fiche de
 * jour ni activités d'agenda**, alors que le contrat les impose (US-CUST-6, cdc §3.4/§8, maquette
 * `:236-238` et `:828-868`). Une case était un `<div>` inerte portant le numéro du jour, et un
 * membre en lecture seule n'avait aucune interaction : ni parent en clair, ni heure de
 * transmission, ni raison de l'attribution, ni activités.
 *
 * Les deux domaines de droit en jeu sont vérifiés séparément — `custody` ouvre la fiche, `agenda`
 * ouvre les activités — parce que c'est précisément le genre de croisement qu'un écran server-rendered
 * laisse fuiter sans qu'aucun test d'API ne s'en aperçoive.
 */
final class GardeDayDetailTest extends WebTestCase
{
    private const string PASSWORD = 'CorrectHorse42!';

    /**
     * Jours matérialisés par le jeu de données, à partir du 1er du mois courant. Trois mois : un
     * jeu limité au mois courant rendait les tests dépendants de la date d'exécution — « demain »
     * sortait de la période générée les derniers jours du mois, et le jour de référence tombait
     * dans le passé les derniers jours aussi, ce qui faisait passer un test du bouton d'échange
     * **actif** alors que la fiche le rendait inerte (même libellé).
     */
    private const int GENERATED_DAYS = 92;

    // ───────────────────────── Pastilles d'activité sur le calendrier ─────────────────────────

    public function testCalendarCarriesActivityBadges(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $day = $this->monthStart()->modify('+2 days');
        $this->seedEvent($day->setTime(17, 30), 'Cours de guitare', EventCategory::Musique);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('garde-day__events', $html);
        self::assertStringContainsString('Cours de guitare', $html);
        // La couleur de la pastille vient de la catégorie, comme sur `/agenda`.
        self::assertStringContainsString('garde-day__event-dot--music', $html);
        // L'information n'est pas que visuelle : la case annonce ses activités aux lecteurs d'écran.
        self::assertStringContainsString('1 activité', $html);
    }

    /**
     * Un événement de plusieurs jours doit se voir sur **chacune** de ses cases : indexer par le
     * seul jour de début (comme le fait la liste de `/agenda`) laissait un séjour de trois jours
     * invisible sur les deux suivants.
     */
    public function testMultiDayEventBadgesEveryCoveredDay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $start = $this->monthStart()->modify('+3 days');
        $this->seedEvent($start->setTime(0, 0), 'Séjour montagne', EventCategory::Autre, $start->modify('+2 days')->setTime(23, 59), allDay: true);

        $client->request('GET', '/garde');
        $html = (string) $client->getResponse()->getContent();

        self::assertSame(3, substr_count($html, 'Séjour montagne'), 'les trois jours couverts portent la pastille');
    }

    /**
     * Une fin posée à minuit pile appartient au jour précédent : sans ce recul, un créneau 18h→00h
     * affichait une pastille sur le lendemain, qui ne le concerne pas.
     */
    public function testEventEndingAtMidnightDoesNotBadgeNextDay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $start = $this->monthStart()->modify('+4 days');
        $this->seedEvent($start->setTime(18, 0), 'Veillée', EventCategory::Autre, $start->modify('+1 day')->setTime(0, 0));

        $client->request('GET', '/garde');
        $html = (string) $client->getResponse()->getContent();

        self::assertSame(1, substr_count($html, 'Veillée'));
    }

    public function testCalendarHidesActivityBadgesWithoutAgendaRight(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(agenda: RightLevel::None);
        $this->login($client);
        $this->seedEvent($this->monthStart()->modify('+2 days')->setTime(17, 30), 'Cours de guitare', EventCategory::Musique);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Ni le titre, ni le nombre : le domaine `agenda` est refusé, donc même l'existence d'une
        // activité ne doit pas transpirer par l'écran de garde.
        self::assertStringNotContainsString('Cours de guitare', $html);
        self::assertStringNotContainsString('garde-day__events', $html);
        self::assertStringNotContainsString('activité', $html);
    }

    // ───────────────────────── Journées d'échange partagées ─────────────────────────

    /**
     * Un jour d'échange n'est pas une journée pleine : à 19:00, les enfants ont passé 79,2 % du
     * jour chez le parent qui les rend. La case porte donc les DEUX teintes, dans cette
     * proportion, et l'annonce aux lecteurs d'écran nomme les deux parents avec l'heure — la
     * proportion, elle, ne se lit que sur la couleur, et ne peut pas être le seul canal.
     *
     * Le jeu de données alterne chaque jour : toutes les cases du mois sont des jours d'échange.
     */
    public function testCalendarSplitsHandoverDaysProportionally(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // 19:00 = 1 140 min sur 1 440 → 79,2 % de la case au parent du jour, le reste à celui qui
        // prend la suite : la transmission se lit sur la DERNIÈRE case du bloc (cdc §3.4).
        self::assertStringContainsString('--split-share: 79.2%', $html);
        self::assertStringContainsString('--split-stops: var(--slot-a-soft) 0% 79.2%, var(--slot-b-soft) 79.2% 100%', $html);
        self::assertStringContainsString('--split-stops: var(--slot-b-soft) 0% 79.2%, var(--slot-a-soft) 79.2% 100%', $html);
        // L'annonce dit chez qui les enfants sont, et de quand à quand.
        self::assertStringContainsString(
            'chez web@example.com de 00:00 à 19:00, chez web-b@example.com de 19:00 à 24:00',
            $html,
        );
        // La légende explique le filet — seul canal du partage en niveaux de gris (cdc §8.1).
        self::assertStringContainsString('garde-legend__split', $html);
        self::assertStringContainsString('le filet marque l\'heure du passage d\'un parent à l\'autre', $html);
    }

    /**
     * Le dernier jour généré n'a pas de lendemain matérialisé : on ne devine pas à qui il rend les
     * enfants, la case reste unie. C'est la situation réelle du bout de l'horizon glissant.
     */
    public function testCalendarDoesNotSplitWhenTheNextDayIsUnknown(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);

        $lastGenerated = $this->monthStart()->modify('+'.(self::GENERATED_DAYS - 1).' days');
        $client->request('GET', '/garde?month='.$lastGenerated->format('Y-m'));
        self::assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        $last = $crawler->filter('[data-day-url$="'.$lastGenerated->format('Y-m-d').'"]');
        self::assertCount(1, $last);
        self::assertStringNotContainsString('garde-day--split', (string) $last->attr('class'));
    }

    /** La fiche du jour DIT le partage, avec l'heure : une couleur ne se vérifie nulle part. */
    public function testDaySheetDescribesTheSharedDay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $day = $this->futureDay();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString(
            'Journée partagée · chez '.$this->expectedParentEmail($day).' de 00:00 à 19:00, chez '.$this->otherParentEmail($day).' de 19:00 à 24:00',
            $html,
        );
    }

    // ───────────────────────── Fiche de jour ─────────────────────────

    public function testDayDetailShowsParentOriginHandoverAndActivities(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $day = $this->futureDay();
        $this->seedEvent($day->setTime(17, 30), 'Cours de guitare', EventCategory::Musique);

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Le fragment doit porter le frame, sinon Turbo n'affiche rien dans la modale.
        self::assertStringContainsString('<turbo-frame id="garde-day"', $html);
        // Chez quel parent — en clair, ce que la case seule ne disait pas.
        self::assertStringContainsString($this->expectedParentEmail($day), $html);
        self::assertStringContainsString('Rythme habituel', $html);
        // Transmission « Retour chez {Parent} le {date} à {heure} » (US-CUST-6) : la journée finit
        // déjà chez l'autre parent (transmission à 19:00), le prochain changement est donc celui du
        // lendemain soir — qui ramène les enfants chez le parent du jour consulté.
        self::assertStringContainsString('Retour chez '.$this->expectedParentEmail($day).' le', $html);
        self::assertStringContainsString('à 19:00', $html);
        // Activités du jour, avec l'horaire dans le fuseau de la famille et la catégorie nommée.
        self::assertStringContainsString('Activités du jour', $html);
        self::assertStringContainsString('Cours de guitare', $html);
        self::assertStringContainsString('17:30', $html);
        self::assertStringContainsString('Musique', $html);
        // Actions (`custody:edit`). L'échange est un FORMULAIRE : jour et heure de début, jour et
        // heure de fin, préremplis avec le créneau que la famille vivrait (cdc §3.4).
        self::assertStringContainsString('data-action="submit->garde#swapDay"', $html);
        self::assertStringContainsString('Échanger ce jour', $html);
        self::assertStringContainsString('name="startTime"', $html);
        self::assertStringContainsString('name="endTime"', $html);
        self::assertStringContainsString('Modifier la garde', $html);
    }

    /** L'origine affichée est celle retenue par le moteur, et l'exception porte sa raison. */
    public function testDayDetailNamesTheExceptionBehindTheAssignment(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$parentA, $parentB] = $this->seedFamily();
        $this->login($client);

        $day = $this->futureDay();
        $em = $this->em();
        $custodyDay = $em->getRepository(CustodyDay::class)->findOneBy(['date' => $day]);
        self::assertInstanceOf(CustodyDay::class, $custodyDay);
        $custodyDay->setParentMembership($parentB)->setSource(CustodyDaySource::Exception);
        $em->persist((new CustodyException())
            ->setFamily($parentA->getFamily())
            ->setStartDate($day)
            ->setEndDate($day)
            ->setParentMembership($parentB)
            ->setReason('Anniversaire'));
        $em->flush();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Exception', $html);
        self::assertStringContainsString('Anniversaire', $html);
        // Jour DÉJÀ échangé : l'action défait l'échange (l'exception d'un jour est retirée) plutôt
        // que d'en empiler une seconde en sens inverse sur la même date.
        self::assertStringContainsString('data-action="garde#revertDay"', $html);
        self::assertStringContainsString("Annuler l'échange", $html);
        self::assertStringNotContainsString('garde#swapDay', $html);
    }

    /**
     * Une exception couvrant PLUSIEURS jours ne s'annule pas depuis la fiche d'un seul : la retirer
     * ici toucherait des dates que l'utilisateur ne regarde pas. L'échange du jour reste offert.
     */
    public function testDayDetailKeepsSwapWhenTheExceptionSpansSeveralDays(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$parentA, $parentB] = $this->seedFamily();
        $this->login($client);

        $day = $this->futureDay();
        $em = $this->em();
        $custodyDay = $em->getRepository(CustodyDay::class)->findOneBy(['date' => $day]);
        self::assertInstanceOf(CustodyDay::class, $custodyDay);
        $custodyDay->setParentMembership($parentB)->setSource(CustodyDaySource::Exception);
        $em->persist((new CustodyException())
            ->setFamily($parentA->getFamily())
            ->setStartDate($day)
            ->setEndDate($day->modify('+3 days'))
            ->setParentMembership($parentB)
            ->setReason('Vacances chez les grands-parents'));
        $em->flush();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('data-action="submit->garde#swapDay"', $html);
        self::assertStringNotContainsString('data-action="garde#revertDay"', $html);
    }

    /**
     * Jour issu d'une règle de vacances : la fiche nomme la période **et** la règle appliquée, avec
     * le même libellé FR que `/garde` et `/settings` — un enum ne doit pas s'écrire de deux façons
     * selon l'écran (le fragment est rendu hors du layout, il redéclare donc ces libellés).
     */
    public function testDayDetailNamesTheHolidayPeriodAndItsRule(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$parentA, $parentB] = $this->seedFamily();
        $this->login($client);

        $day = $this->futureDay();
        $em = $this->em();
        $custodyDay = $em->getRepository(CustodyDay::class)->findOneBy(['date' => $day]);
        self::assertInstanceOf(CustodyDay::class, $custodyDay);
        $custodyDay->setParentMembership($parentB)->setSource(CustodyDaySource::Holiday);

        $holiday = (new SchoolHoliday())
            ->setFamily($parentA->getFamily())
            ->setLabel("Vacances d'Automne")
            ->setStartDate($day->modify('-1 day'))
            ->setEndDate($day->modify('+5 days'))
            ->setSource(HolidaySource::Manual);
        $rule = (new HolidayRule())->setSchoolHoliday($holiday)->setKind(HolidayRuleKind::AlternatingWeeks)->setParams(['startParent' => 'B']);
        $holiday->setRule($rule);
        $em->persist($holiday);
        $em->persist($rule);
        $em->flush();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // L'apostrophe du libellé ressort échappée par Twig (`&#039;`), comme sur tout autre écran.
        self::assertStringContainsString('Vacances d&#039;Automne', $html);
        self::assertStringContainsString('Une semaine sur deux', $html);
    }

    public function testDayDetailHidesActivitiesWithoutAgendaRight(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(agenda: RightLevel::None);
        $this->login($client);
        $day = $this->futureDay();
        $this->seedEvent($day->setTime(17, 30), 'Cours de guitare', EventCategory::Musique);

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // La section entière disparaît : son état vide (« Aucune activité ce jour ») dirait déjà
        // s'il y a ou non des activités.
        self::assertStringNotContainsString('Activités du jour', $html);
        self::assertStringNotContainsString('Cours de guitare', $html);
        // La garde, elle, reste consultable : les deux domaines sont indépendants.
        self::assertStringContainsString('Rythme habituel', $html);
    }

    /**
     * `custody=view` : l'information, pas les actions. Les présenter pour se solder par un 403 —
     * ce que fait encore l'app mobile (constat C? de la revue Flutter) — est le défaut à éviter.
     */
    public function testDayDetailHidesEditActionsForViewOnlyMember(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(custody: RightLevel::View);
        $this->login($client);
        $day = $this->futureDay();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Retour chez', $html); // l'information reste due
        self::assertStringNotContainsString('Échanger ce jour', $html);
        self::assertStringNotContainsString('Modifier la garde', $html);
    }

    /** L'API refuse une exception dans le passé (422) : le bouton est présenté inerte, pas absent. */
    public function testDayDetailDisablesSwapOnPastDay(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$parentA] = $this->seedFamily();
        $this->login($client);

        // Hier est déjà matérialisé sauf le 1er du mois, où il appartient au mois précédent : on
        // complète au besoin plutôt que d'insérer en double (contrainte `uniq_custody_day`).
        $yesterday = $this->today()->modify('-1 day');
        $em = $this->em();
        if (null === $em->getRepository(CustodyDay::class)->findOneBy(['date' => $yesterday])) {
            $em->persist((new CustodyDay())
                ->setFamily($parentA->getFamily())
                ->setDate($yesterday)
                ->setParentMembership($parentA)
                ->setSource(CustodyDaySource::Base));
            $em->flush();
        }

        $client->request('GET', '/garde/jour/'.$yesterday->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('Un jour passé ne peut plus être échangé', $html);
        self::assertStringNotContainsString('garde#swapDay', $html);
    }

    /** Un jour hors de la période générée n'invente ni parent ni transmission. */
    public function testDayDetailStatesWhenDayIsNotMaterialised(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $far = $this->monthStart()->modify('+8 months'); // bien au-delà de la période générée

        $client->request('GET', '/garde/jour/'.$far->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString("Ce jour n'est pas encore attribué", $html);
        self::assertStringNotContainsString('Retour chez', $html);
        self::assertStringNotContainsString('garde#swapDay', $html);
    }

    /**
     * En bout d'horizon généré, aucun changement d'attributaire ne suit : la fiche n'annonce alors
     * pas de transmission plutôt que d'en inventer une à partir du rythme théorique.
     */
    public function testDayDetailOmitsHandoverAtTheEndOfTheGeneratedHorizon(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);
        $lastGenerated = $this->monthStart()->modify('+'.(self::GENERATED_DAYS - 1).' days');

        $client->request('GET', '/garde/jour/'.$lastGenerated->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString($this->expectedParentEmail($lastGenerated), $html); // le jour EST attribué
        self::assertStringNotContainsString('Retour chez', $html);
    }

    /**
     * Sans droit `custody`, la fiche ne rend **rien** du jour — même refus que l'écran (revue
     * 2026-08 C7), et le refus reste enveloppé dans le frame pour s'afficher dans la modale.
     */
    public function testDayDetailIsRefusedWithoutCustodyRight(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(custody: RightLevel::None);
        $this->login($client);
        $day = $this->futureDay();

        $client->request('GET', '/garde/jour/'.$day->format('Y-m-d'));
        self::assertResponseStatusCodeSame(403);
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('<turbo-frame id="garde-day"', $html);
        self::assertStringContainsString("Vous n'avez pas accès au planning de garde", $html);
        self::assertStringNotContainsString('Retour chez', $html);
        self::assertStringNotContainsString('web-b@example.com', $html);
    }

    public function testDayDetailRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/garde/jour/'.$this->monthStart()->format('Y-m-d'));

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * `createFromFormat` est permissif (2026-02-31 → 2026-03-03) : une date inexistante doit être
     * rejetée, pas silencieusement décalée sur un autre jour que celui demandé.
     */
    public function testDayDetailRejectsImpossibleDate(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);

        $client->request('GET', '/garde/jour/2026-02-31');
        self::assertResponseStatusCodeSame(404);

        $client->request('GET', '/garde/jour/2026-13-01');
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * Le clic sur une case ouvre la FICHE, pour tout membre `custody=view` — et n'échange plus le
     * jour directement : `swapDay` était câblé sur la case elle-même, si bien qu'un clic (ou la
     * touche Espace en parcourant le planning au clavier) réattribuait durablement un jour de garde
     * derrière un simple `confirm()`.
     */
    public function testCalendarCellsOpenTheDaySheetInsteadOfSwapping(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(custody: RightLevel::View);
        $this->login($client);

        $client->request('GET', '/garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('click->garde#openDay', $html);
        self::assertStringContainsString('data-day-url="/garde/jour/', $html);
        // Un membre en lecture seule a désormais une interaction : la case est un bouton.
        self::assertStringContainsString('role="button"', $html);
        // Plus aucun échange déclenché par la case, quel que soit le droit.
        self::assertStringNotContainsString('click->garde#swapDay', $html);
    }

    // ───────────────────────── Fixtures ─────────────────────────

    private function em(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function today(): \DateTimeImmutable
    {
        return Clock::get()->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->setTime(0, 0);
    }

    private function monthStart(): \DateTimeImmutable
    {
        return $this->today()->modify('first day of this month');
    }

    /** Jour de référence des scénarios « à venir » : toujours matérialisé, jamais passé. */
    private function futureDay(): \DateTimeImmutable
    {
        return $this->today()->modify('+2 days');
    }

    /**
     * Parent attendu pour un jour donné, selon la règle du jeu de données (alternance quotidienne
     * depuis le 1er du mois courant). Recalculé ici plutôt que codé en dur : le jour de référence
     * étant relatif à aujourd'hui, sa parité change d'un jour à l'autre.
     */
    private function expectedParentEmail(\DateTimeImmutable $day): string
    {
        $index = (int) $this->monthStart()->diff($day)->days;

        return 0 === $index % 2 ? 'web@example.com' : 'web-b@example.com';
    }

    /** L'autre parent : celui chez qui les enfants partent au prochain changement. */
    private function otherParentEmail(\DateTimeImmutable $day): string
    {
        return 'web@example.com' === $this->expectedParentEmail($day) ? 'web-b@example.com' : 'web@example.com';
    }

    /**
     * Famille séparée à deux parents, rythme alterné ancré au 1er du mois, et les jours du mois
     * matérialisés en alternance quotidienne (index pair → parent A) : c'est cette alternance qui
     * rend la transmission du lendemain calculable.
     *
     * @return array{0: Membership, 1: Membership} parent A (le compte connecté) et parent B
     */
    private function seedFamily(RightLevel $custody = RightLevel::Edit, RightLevel $agenda = RightLevel::Edit): array
    {
        $em = $this->em();
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        $user = (new User())->setEmail('web@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);

        $family = (new Family())->setName('Famille Web')->setType(FamilyType::Separated);
        $em->persist($family);

        // Le rôle `parent` force `edit` partout (invariant) : restreindre un droit impose donc un
        // beau-parent, dont les droits sont ceux qu'on lui accorde explicitement.
        $restricted = RightLevel::Edit !== $custody || RightLevel::Edit !== $agenda;
        $parentA = (new Membership())->setUser($user)->setFamily($family)
            ->setRole($restricted ? MembershipRole::StepParent : MembershipRole::Parent)
            ->setIsAdmin(!$restricted)->setStatus(MembershipStatus::Active);
        if ($restricted) {
            $parentA->addRight(new Right(RightDomain::Custody, $custody));
            $parentA->addRight(new Right(RightDomain::Agenda, $agenda));
        }
        $em->persist($parentA);

        $userB = (new User())->setEmail('web-b@example.com')->setStatus(UserStatus::Active)
            ->setEmailVerifiedAt(Clock::get()->now());
        $userB->setPasswordHash('x');
        $parentB = (new Membership())->setUser($userB)->setFamily($family)
            ->setRole(MembershipRole::Parent)->setIsAdmin(false)->setStatus(MembershipStatus::Active);
        $em->persist($userB);
        $em->persist($parentB);

        // Le rythme exige deux membres `parent` distincts : quand le compte connecté est restreint
        // (donc beau-parent), un troisième membre tient le rôle de parent A du planning.
        $slotA = $parentA;
        if ($restricted) {
            $userC = (new User())->setEmail('web-a@example.com')->setStatus(UserStatus::Active)
                ->setEmailVerifiedAt(Clock::get()->now());
            $userC->setPasswordHash('x');
            $slotA = (new Membership())->setUser($userC)->setFamily($family)
                ->setRole(MembershipRole::Parent)->setIsAdmin(true)->setStatus(MembershipStatus::Active);
            $em->persist($userC);
            $em->persist($slotA);
        }

        $monthStart = $this->monthStart();
        $em->persist((new CustodyPattern())
            ->setFamily($family)
            ->setKind(CustodyPatternKind::Alternating)
            ->setAnchorDate($monthStart)
            ->setHandoverWeekday(0)
            ->setHandoverTime('19:00')
            ->setParentA($slotA)
            ->setParentB($parentB));

        // Jours matérialisés comme le ferait le moteur : alternance quotidienne, donc CHAQUE jour
        // est un dernier jour de bloc et porte le découpage « parent du jour jusqu'à 19:00, parent
        // du lendemain ensuite » (cdc §3.4). Le dernier jour généré n'a pas de lendemain connu : il
        // reste uni, comme en bout d'horizon glissant.
        $parentOf = static fn (int $i): Membership => 0 === $i % 2 ? $slotA : $parentB;
        for ($i = 0; $i < self::GENERATED_DAYS; ++$i) {
            $next = $i + 1 < self::GENERATED_DAYS ? (string) $parentOf($i + 1)->getId() : null;
            $segments = DaySegments::nominal(
                (string) $parentOf($i)->getId(),
                $next,
                DaySegment::minutesOf('19:00'),
                CustodyDaySource::Base,
                CustodyDaySource::Base,
            );
            $em->persist((new CustodyDay())
                ->setFamily($family)
                ->setDate($monthStart->modify("+{$i} day"))
                // 19:00 : le parent du jour en garde 79,2 %, c'est donc lui le majoritaire.
                ->setParentMembership($parentOf($i))
                ->setSource(CustodyDaySource::Base)
                ->setSegments($segments->isUniform() ? null : $segments->toArray()));
        }

        $em->flush();

        return [$slotA, $parentB];
    }

    /**
     * Beau-parent actif associé à [$parent] (évolution E1). La relation est portée par le
     * beau-parent — c'est la source de vérité unique, la vue inverse se déduit.
     */
    // ───────────────────────── Foyer (E2) et couleurs du planning (E5) ─────────────────────────

    /**
     * Foyer (E2) : le beau-parent associé au parent accompagne les jours de « son » parent.
     *
     * C'est une mention de **présentation** : le jour reste attribué au seul parent, rien n'est
     * compté au conjoint et le moteur de garde ignore la relation (invariant `CLAUDE.md`).
     */
    public function testDaySheetNamesTheHousehold(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$slotA] = $this->seedFamily();
        $this->seedPartner($slotA, 'Bob Martin');
        $this->login($client);

        // Le 1er du mois est attribué au parent A par le jeu de données.
        $client->request('GET', '/garde/jour/'.$this->monthStart()->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('day-sheet__partner', $html);
        self::assertStringContainsString('avec Bob Martin', $html);
    }

    /** Le jour du parent SANS conjoint ne porte aucune mention : elle serait vide de sens. */
    public function testDaySheetOmitsHouseholdWithoutPartner(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$slotA] = $this->seedFamily();
        $this->seedPartner($slotA, 'Bob Martin');
        $this->login($client);

        // Le 2 du mois revient au parent B, qui n'a pas de conjoint.
        $client->request('GET', '/garde/jour/'.$this->monthStart()->modify('+1 day')->format('Y-m-d'));
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('day-sheet__partner', (string) $client->getResponse()->getContent());
    }

    /**
     * Couleurs du planning (E5) : sans préférence, les variables valent les deux teintes par défaut
     * du §8 — l'écran est celui d'avant la personnalisation.
     */
    public function testCustodyPaletteDefaultsToSectionEightColours(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily();
        $this->login($client);

        $client->request('GET', '/garde');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('--slot-a:#C2643D', $html);
        self::assertStringContainsString('--slot-b:#2E7D71', $html);
    }

    /**
     * La préférence enregistrée repeint le planning **du seul spectateur** : les variables posées
     * sur le conteneur applicatif changent, celles de l'autre parent restent celles du §8.
     */
    public function testCustodyPaletteFollowsTheViewerPreference(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        [$slotA, $parentB] = $this->seedFamily();
        $em = $this->em();
        $viewer = $em->getRepository(Membership::class)->find($slotA->getId());
        self::assertInstanceOf(Membership::class, $viewer);
        $target = $em->getRepository(Membership::class)->find($parentB->getId());
        self::assertInstanceOf(Membership::class, $target);
        $em->persist(new CustodyColorPreference($viewer, $target, CustodyPalette::Purple));
        $em->flush();

        $this->login($client);
        $client->request('GET', '/garde');
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('--slot-b:#8E63A8', $html, 'le parent B est repeint en violet');
        self::assertStringContainsString('--slot-a:#C2643D', $html, 'le parent A garde la teinte par défaut');
        // §8.1 sans exception : l'initiale reste affichée quelle que soit la palette.
        self::assertStringContainsString('garde-day__parent', $html);
    }

    /**
     * Le choix des couleurs relève de `custody ≥ view` (cdc §8.2), contrairement au rythme : un
     * beau-parent en lecture doit y accéder, sans quoi la préférence serait réservée à ceux qui
     * peuvent déjà tout changer.
     */
    public function testColourChoiceIsOfferedToAViewOnlyMember(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->seedFamily(custody: RightLevel::View, agenda: RightLevel::View);
        $this->login($client);

        $client->request('GET', '/settings?tab=garde');
        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        self::assertStringContainsString('id="sec-colors"', $html);
        self::assertStringContainsString('custody-color-A', $html);
        // …mais pas le formulaire de rythme, qui reste sous `custody:edit`.
        self::assertStringNotContainsString('garde#savePattern', $html);
    }

    private function seedPartner(Membership $parent, string $fullName): Membership
    {
        $em = $this->em();
        $user = (new User())->setEmail('web-step@example.com')->setFullName($fullName)
            ->setStatus(UserStatus::Active)->setEmailVerifiedAt(Clock::get()->now());
        $user->setPasswordHash('x');
        $step = (new Membership())->setUser($user)->setFamily($parent->getFamily())
            ->setRole(MembershipRole::StepParent)->setStatus(MembershipStatus::Active)
            ->setPartnerMembership($parent);
        $em->persist($user);
        $em->persist($step);
        $em->flush();

        return $step;
    }

    private function seedEvent(
        \DateTimeImmutable $startsAt,
        string $title,
        EventCategory $category,
        ?\DateTimeImmutable $endsAt = null,
        bool $allDay = false,
    ): void {
        $em = $this->em();
        $family = $em->getRepository(Family::class)->findOneBy(['name' => 'Famille Web']);
        self::assertInstanceOf(Family::class, $family);

        $child = (new Child())->setFamily($family)->setFirstName('Léa')->setColor('#C2643D');
        $em->persist($child);

        $event = (new Event())
            ->setFamily($family)
            ->setTitle($title)
            ->setCategory($category)
            ->setAllDay($allDay)
            ->setStartsAt($startsAt->setTimezone(new \DateTimeZone('UTC')))
            ->setEndsAt($endsAt?->setTimezone(new \DateTimeZone('UTC')))
            ->setWholeFamily(false);
        $event->addChild($child);
        $em->persist($event);
        $em->flush();
    }

    private function login(KernelBrowser $client): void
    {
        $client->request('POST', '/api/auth/login', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: json_encode(['email' => 'web@example.com', 'password' => self::PASSWORD], \JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
