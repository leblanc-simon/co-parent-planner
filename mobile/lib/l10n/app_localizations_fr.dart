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
  String get tabAgenda => 'Agenda';

  @override
  String get tabNotes => 'Enfants';

  @override
  String get tabSettings => 'Reglages';

  @override
  String get homeGreeting => 'Bonjour';

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
}
