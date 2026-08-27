// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for French (`fr`).
class AppL10nFr extends AppL10n {
  AppL10nFr([String locale = 'fr']) : super(locale);

  @override
  String get appTitle => 'CoParentPlanner';

  @override
  String get actionContinue => 'Continuer';

  @override
  String get actionCancel => 'Annuler';

  @override
  String get actionRetry => 'Reessayer';

  @override
  String get actionSave => 'Enregistrer';

  @override
  String get genericError => 'Une erreur est survenue. Veuillez reessayer.';

  @override
  String get fieldRequired => 'Ce champ est requis.';

  @override
  String get emailLabel => 'Adresse e-mail';

  @override
  String get passwordLabel => 'Mot de passe';

  @override
  String get invalidEmail => 'Adresse e-mail invalide.';

  @override
  String get loginTitle => 'Connexion';

  @override
  String get loginSubtitle => 'Accedez a l\'espace de votre famille.';

  @override
  String get loginAction => 'Se connecter';

  @override
  String get loginForgot => 'Mot de passe oublie ?';

  @override
  String get loginNoAccount => 'Pas encore de compte ?';

  @override
  String get loginRegisterLink => 'Creer un compte';

  @override
  String get registerTitle => 'Creer un compte';

  @override
  String get registerSubtitle => 'Quelques informations pour demarrer.';

  @override
  String get registerFullName => 'Nom complet (optionnel)';

  @override
  String get registerPhone => 'Telephone (optionnel)';

  @override
  String get registerAction => 'Creer mon compte';

  @override
  String get registerHasAccount => 'Deja un compte ?';

  @override
  String get registerLoginLink => 'Se connecter';

  @override
  String get passwordPolicyHint => 'Au moins 12 caracteres.';

  @override
  String get passwordTooShort =>
      'Le mot de passe doit comporter au moins 12 caracteres.';

  @override
  String get checkEmailTitle => 'Verifiez votre e-mail';

  @override
  String checkEmailBody(String email) {
    return 'Un e-mail de verification a ete envoye a $email. Ouvrez le lien qu\'il contient pour activer votre compte.';
  }

  @override
  String get checkEmailBackToLogin => 'Revenir a la connexion';

  @override
  String get forgotTitle => 'Mot de passe oublie';

  @override
  String get forgotSubtitle =>
      'Saisissez votre e-mail pour recevoir un lien de reinitialisation.';

  @override
  String get forgotAction => 'Envoyer le lien';

  @override
  String get forgotGenericSuccess =>
      'Si un compte existe pour cette adresse, un e-mail de reinitialisation vient d\'etre envoye.';

  @override
  String get resetTitle => 'Nouveau mot de passe';

  @override
  String get resetSubtitle => 'Choisissez un nouveau mot de passe.';

  @override
  String get resetNewPassword => 'Nouveau mot de passe';

  @override
  String get resetConfirmPassword => 'Confirmer le mot de passe';

  @override
  String get resetAction => 'Reinitialiser';

  @override
  String get resetPasswordsMismatch =>
      'Les mots de passe ne correspondent pas.';

  @override
  String get resetSuccess =>
      'Votre mot de passe a ete reinitialise. Vous pouvez vous connecter.';

  @override
  String get verifyInProgress => 'Verification en cours...';

  @override
  String get verifySuccess =>
      'Votre adresse e-mail est verifiee. Vous pouvez vous connecter.';

  @override
  String get verifyError => 'Lien invalide ou expire.';

  @override
  String get verifyGoToLogin => 'Aller a la connexion';

  @override
  String get emailNotVerified =>
      'Veuillez verifier votre adresse e-mail avant de vous connecter.';

  @override
  String get sessionExpired => 'Session expiree, veuillez vous reconnecter.';

  @override
  String get onboardingTitle => 'Bienvenue';

  @override
  String get onboardingSubtitle =>
      'Creez votre premiere famille ou rejoignez-en une via une invitation.';

  @override
  String get onboardingCreate => 'Creer une famille';

  @override
  String get onboardingJoin => 'Rejoindre via une invitation';

  @override
  String get onboardingPickExisting => 'Reprendre une famille existante';

  @override
  String get onboardingSubtitleWithFamilies =>
      'Choisissez la famille à ouvrir, ou créez-en une nouvelle.';

  @override
  String get createFamilyTitle => 'Creer une famille';

  @override
  String get familyNameLabel => 'Nom de la famille';

  @override
  String get familyTypeLabel => 'Type de famille';

  @override
  String get familyTypeSeparated => 'Parents separes';

  @override
  String get familyTypeIntact => 'Famille unie';

  @override
  String get createFamilyAction => 'Creer la famille';

  @override
  String get joinFamilyTitle => 'Rejoindre une famille';

  @override
  String get joinFamilySubtitle => 'Saisissez le code d\'invitation recu.';

  @override
  String get invitationTokenLabel => 'Code d\'invitation';

  @override
  String get joinFamilyAction => 'Rejoindre';

  @override
  String get tabHome => 'Accueil';

  @override
  String get tabCustody => 'Garde';

  @override
  String get tabAgenda => 'Agenda';

  @override
  String get tabNotes => 'Enfants';

  @override
  String get tabSettings => 'Reglages';

  @override
  String get homeGreeting => 'Bonjour';

  @override
  String get homeAllAgenda => 'Tout l\'agenda';

  @override
  String get homeUpcomingTitle => 'A venir';

  @override
  String get homeUpcomingEmpty => 'Aucun evenement a venir.';

  @override
  String get homeDecisionsTitle => 'A decider';

  @override
  String get homeDecisionsEmpty => 'Rien a decider pour le moment.';

  @override
  String get homeNotesTitle => 'Dernieres notes';

  @override
  String get homeNotesEmpty => 'Aucune note pour le moment.';

  @override
  String get homeCustodyTitle => 'Garde de la semaine';

  @override
  String get homeCustodyEmpty => 'Aucun planning de garde pour le moment.';

  @override
  String get homeFamilyIntact => 'Toute la famille reunie.';

  @override
  String get settingsTitle => 'Reglages';

  @override
  String get settingsProfile => 'Profil';

  @override
  String get settingsFullName => 'Nom complet';

  @override
  String get settingsPhone => 'Telephone';

  @override
  String get settingsLanguage => 'Langue';

  @override
  String get settingsLogout => 'Se deconnecter';

  @override
  String get settingsProfileUpdated => 'Profil mis a jour.';

  @override
  String get settingsActiveFamily => 'Famille active';

  @override
  String get settingsSwitchFamily => 'Changer de famille';

  @override
  String get settingsMembers => 'Membres de la famille';

  @override
  String get membersTitle => 'Membres';

  @override
  String get membersEmpty => 'Aucun membre.';

  @override
  String get membersRoleParent => 'Parent';

  @override
  String get membersRoleStepParent => 'Beau-parent';

  @override
  String get membersRoleChild => 'Enfant';

  @override
  String get membersAdminBadge => 'admin';

  @override
  String get membersInvite => 'Inviter une personne';

  @override
  String get membersPending => 'Invitations en attente';

  @override
  String get membersRevoke => 'Revoquer';

  @override
  String get membersInvited => 'Invitation envoyee.';

  @override
  String get membersRevoked => 'Invitation revoquee.';

  @override
  String get inviteTitle => 'Inviter une personne';

  @override
  String get inviteEmail => 'Adresse e-mail';

  @override
  String get inviteRole => 'Role';

  @override
  String get inviteSend => 'Envoyer l\'invitation';

  @override
  String get inviteRightAgenda => 'Agenda';

  @override
  String get inviteRightCustody => 'Garde';

  @override
  String get inviteRightNotes => 'Notes enfants';

  @override
  String get inviteRightExchanges => 'Echanges';

  @override
  String get rightNone => 'Aucun';

  @override
  String get rightView => 'Lecture';

  @override
  String get rightEdit => 'Edition';

  @override
  String get settingsChildren => 'Enfants';

  @override
  String get childrenTitle => 'Enfants';

  @override
  String get childrenEmpty => 'Aucun enfant pour le moment.';

  @override
  String childAge(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count ans',
      one: '$count an',
      zero: 'Moins d\'un an',
    );
    return '$_temp0';
  }

  @override
  String get childInfoTitle => 'Informations';

  @override
  String get childSchool => 'Scolarité';

  @override
  String get childNotesTitle => 'Notes';

  @override
  String get childNotesEmpty => 'Aucune note.';

  @override
  String get childAddNote => 'Ajouter une note';

  @override
  String get childNotePlaceholder => 'Saisir une note…';

  @override
  String get childNoteSend => 'Publier';

  @override
  String get childHealthWarning =>
      'Ne saisissez pas de données de santé (allergies, traitements, diagnostics). Informations pratiques générales uniquement.';

  @override
  String get custodyTitle => 'Garde';

  @override
  String get custodyScreenTitle => 'Planning de garde';

  @override
  String custodyRuleHandover(String weekday, String time) {
    return 'Échange le $weekday à $time';
  }

  @override
  String get custodyActivity => 'Activité';

  @override
  String get custodyOpen => 'Ouvrir le planning';

  @override
  String get custodyIntact =>
      'La garde ne s\'applique qu\'aux familles séparées.';

  @override
  String get custodyNoPattern => 'Aucun rythme de garde configuré.';

  @override
  String get custodyConfigure => 'Configurer le rythme';

  @override
  String get custodyParentA => 'Parent A';

  @override
  String get custodyParentB => 'Parent B';

  @override
  String get custodyExceptional => 'Exceptionnel';

  @override
  String get custodyHoliday => 'Vacances';

  @override
  String get custodyStatsTitle => 'Statistiques du mois';

  @override
  String custodyStatsDays(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count jours',
      one: '$count jour',
      zero: 'aucun jour',
    );
    return '$_temp0';
  }

  @override
  String get custodyDayDetailTitle => 'Détail du jour';

  @override
  String get custodySource => 'Origine';

  @override
  String get custodySourceBase => 'Rythme de base';

  @override
  String get custodySourceHoliday => 'Vacances';

  @override
  String get custodySourceException => 'Exception';

  @override
  String get custodySwapDay => 'Échanger ce jour';

  @override
  String get custodyAssignedTo => 'Confié à';

  @override
  String get custodyNoParent => 'Non attribué';

  @override
  String get custodySwapped => 'Jour échangé.';

  @override
  String get custodySwapUnavailable =>
      'Échange impossible : l\'autre parent n\'est pas défini.';

  @override
  String get custodyParentsRequired => 'Choisissez deux parents distincts.';

  @override
  String get custodyExceptionsTitle => 'Exceptions';

  @override
  String get custodyExceptionsEmpty => 'Aucune exception.';

  @override
  String get custodyAddException => 'Ajouter une exception';

  @override
  String get custodyStartDate => 'Début';

  @override
  String get custodyEndDate => 'Fin';

  @override
  String get custodyParent => 'Parent';

  @override
  String get custodyReason => 'Motif (optionnel)';

  @override
  String get custodyDelete => 'Supprimer';

  @override
  String get custodyExceptionAdded => 'Exception ajoutée.';

  @override
  String get custodyExceptionDeleted => 'Exception supprimée.';

  @override
  String get custodyHolidaysTitle => 'Vacances scolaires';

  @override
  String get custodyHolidaysEmpty => 'Aucune période de vacances.';

  @override
  String get custodyImportHolidays => 'Importer les vacances officielles';

  @override
  String get custodyHolidaysImported => 'Import des vacances lancé.';

  @override
  String get custodySettingsTitle => 'Rythme de garde';

  @override
  String get custodyKind => 'Modèle';

  @override
  String get custodyKindAlternating => 'Une semaine sur deux';

  @override
  String get custodyKind223 => '2-2-3';

  @override
  String get custodyKindCustom => 'Personnalisé (14 jours)';

  @override
  String get custodyAnchorDate => 'Date d\'ancrage';

  @override
  String get custodyHandoverWeekday => 'Jour d\'échange';

  @override
  String get custodyHandoverTime => 'Heure d\'échange';

  @override
  String get custodyCustomPattern => 'Motif personnalisé (14 nuits)';

  @override
  String get custodySave => 'Enregistrer le rythme';

  @override
  String get custodySaved => 'Rythme enregistré.';

  @override
  String get custodyPatternSaving => 'Génération du planning en cours…';

  @override
  String get weekdaySunday => 'Dimanche';

  @override
  String get weekdayMonday => 'Lundi';

  @override
  String get weekdayTuesday => 'Mardi';

  @override
  String get weekdayWednesday => 'Mercredi';

  @override
  String get weekdayThursday => 'Jeudi';

  @override
  String get weekdayFriday => 'Vendredi';

  @override
  String get weekdaySaturday => 'Samedi';

  @override
  String get agendaTitle => 'Agenda';

  @override
  String get agendaEmpty => 'Aucun événement ce mois-ci.';

  @override
  String get agendaFilterAll => 'Tout';

  @override
  String agendaDayEvents(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count événements',
      one: '$count événement',
      zero: 'Aucun événement',
    );
    return '$_temp0';
  }

  @override
  String get agendaNew => 'Nouvel événement';

  @override
  String get agendaCreate => 'Créer';

  @override
  String get agendaCreated => 'Événement créé.';

  @override
  String get agendaFieldTitle => 'Titre';

  @override
  String get agendaCategory => 'Catégorie';

  @override
  String get agendaResponsible => 'Responsable';

  @override
  String get agendaWholeFamily => 'Toute la famille';

  @override
  String get agendaChildren => 'Enfant(s)';

  @override
  String get agendaAllDay => 'Journée entière';

  @override
  String get agendaStart => 'Début';

  @override
  String get agendaEnd => 'Fin (optionnelle)';

  @override
  String get agendaLocation => 'Lieu';

  @override
  String get agendaAllDayLabel => 'Journée';

  @override
  String get agendaUpcomingSee => 'Voir l\'agenda';

  @override
  String get catSport => 'Sport';

  @override
  String get catMusic => 'Musique';

  @override
  String get catSchool => 'École';

  @override
  String get catHealth => 'Santé';

  @override
  String get catOther => 'Autre';

  @override
  String get overlaySectionTitle => 'Overlay familles liées';

  @override
  String get overlayLinkedFamily => 'Famille liée';

  @override
  String overlayToggle(String name) {
    return 'Overlay famille liée · $name';
  }

  @override
  String get overlayEmpty =>
      'Aucune activité de la famille liée sur cette période.';

  @override
  String get exchangesTitle => 'Échanges';

  @override
  String get exchangesEmpty => 'Aucun échange pour le moment.';

  @override
  String get exchangesNew => 'Nouvel échange';

  @override
  String get exchangesFilterOpen => 'Ouverts';

  @override
  String get exchangesFilterDecided => 'Décidés';

  @override
  String exchangesOpenCount(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count échanges ouverts',
      one: '$count échange ouvert',
      zero: 'Aucun échange ouvert',
    );
    return '$_temp0';
  }

  @override
  String get exchangeTypeDiscussion => 'Discussion';

  @override
  String get exchangeTypeDecision => 'Décision';

  @override
  String get exchangeWholeFamily => 'Toute la famille';

  @override
  String get exchangeOverdue => 'En retard';

  @override
  String get exchangeDecided => 'Décidé';

  @override
  String get exchangeAnonymous => 'Membre supprimé';

  @override
  String exchangeDeadlineLabel(String date) {
    return 'Échéance : $date';
  }

  @override
  String get exchangeThread => 'Fil de discussion';

  @override
  String get exchangeThreadEmpty => 'Aucun message pour l\'instant.';

  @override
  String get exchangeMessagePlaceholder => 'Écrire un message…';

  @override
  String get exchangeDecidedBanner => 'Décision tranchée';

  @override
  String get exchangeValidateOption => 'Valider cette option';

  @override
  String exchangeVotesCount(num count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count validations',
      one: '$count validation',
      zero: 'Aucune validation',
    );
    return '$_temp0';
  }

  @override
  String get exchangeConflict => 'Cette décision est déjà tranchée.';

  @override
  String get exchangeClose => 'Clôturer';

  @override
  String get exchangeClosed => 'Échange clôturé.';

  @override
  String get exchangeCreate => 'Créer';

  @override
  String get exchangeCreated => 'Échange créé.';

  @override
  String get exchangeFieldTitle => 'Titre';

  @override
  String get exchangeFieldBody => 'Message ou contexte (optionnel)';

  @override
  String get exchangeChooseChild => 'Enfant concerné';

  @override
  String get exchangeFieldDeadline => 'Échéance (optionnelle)';

  @override
  String get exchangeNoDeadline => 'Aucune échéance';

  @override
  String get exchangeFieldOptions => 'Options';

  @override
  String get exchangeOptionLabel => 'Option';

  @override
  String get exchangeAddOption => 'Ajouter une option';

  @override
  String get exchangeOptionsHint =>
      'Une décision requiert au moins deux options.';

  @override
  String get notificationsTitle => 'Notifications';

  @override
  String get notificationsEmailSection => 'Notifications e-mail';

  @override
  String get notifCategoryCustody => 'Garde';

  @override
  String get notifCategoryAgenda => 'Agenda';

  @override
  String get notifCategoryExchanges => 'Échanges';

  @override
  String get notifCategoryInvitation => 'Invitations';

  @override
  String get notificationsQuietSection => 'Heures silencieuses';

  @override
  String get notificationsQuietHint =>
      'Aucun e-mail ne sera envoyé pendant cette plage (fuseau de la famille).';

  @override
  String get notificationsQuietStart => 'Début';

  @override
  String get notificationsQuietEnd => 'Fin';

  @override
  String get notificationsQuietUnset => 'Non définie';

  @override
  String get notificationsSaved => 'Préférences enregistrées.';

  @override
  String get offlineBanner =>
      'Hors-ligne — affichage des données mises en cache';

  @override
  String get offlineUnavailable => 'Données non disponibles hors-ligne';

  @override
  String get offlineQueued => 'Enregistré — en attente de synchro';

  @override
  String syncPending(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count modifications en attente de synchro',
      one: '1 modification en attente de synchro',
      zero: 'Tout est synchronisé',
    );
    return '$_temp0';
  }

  @override
  String syncRejected(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count modifications refusées',
      one: '1 modification refusée',
      zero: '',
    );
    return '$_temp0';
  }

  @override
  String get syncRejectedTitle => 'Modifications refusées';

  @override
  String get syncRejectedHelp =>
      'Le serveur a refusé ces modifications enregistrées hors-ligne. Relancez-les ou abandonnez-les.';

  @override
  String syncRejectedReason(String reason) {
    String _temp0 = intl.Intl.selectLogic(reason, {
      'conflict': 'Conflit : la donnée a changé entre-temps',
      'forbidden': 'Droits insuffisants',
      'invalid': 'Contenu refusé par le serveur',
      'gone': 'Lélément nexiste plus',
      'other': 'Refusé par le serveur',
    });
    return '$_temp0';
  }

  @override
  String get syncRejectedAction => 'Voir';

  @override
  String get actionDiscard => 'Abandonner';

  @override
  String get syncRejectedEmpty => 'Aucune modification refusée.';

  @override
  String get privacyTitle => 'Confidentialité & données';

  @override
  String get privacyExportSection => 'Exporter mes données';

  @override
  String get privacyExportHint =>
      'Recevez une archive de vos données personnelles (droit d\'accès et de portabilité).';

  @override
  String get privacyExportButton => 'Demander l\'export';

  @override
  String get privacyExportPending => 'Export en cours de préparation…';

  @override
  String get privacyExportRefresh => 'Actualiser le statut';

  @override
  String get privacyExportReady => 'Votre export est prêt (lien temporaire) :';

  @override
  String get privacyDeleteSection => 'Supprimer mon compte';

  @override
  String get privacyDeleteHint =>
      'Vos données personnelles sont anonymisées de façon irréversible. Vos messages et notes sont conservés sous « Membre supprimé » pour préserver les fils.';

  @override
  String get privacyDeleteButton => 'Supprimer mon compte';

  @override
  String get privacyDeleteConfirmTitle => 'Supprimer définitivement ?';

  @override
  String get privacyDeleteConfirmBody =>
      'Cette action est irréversible : votre compte sera anonymisé et vous serez déconnecté.';

  @override
  String get privacyDeleteConfirm => 'Supprimer';

  @override
  String get privacyCancel => 'Annuler';
}
