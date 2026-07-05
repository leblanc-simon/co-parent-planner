import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_fr.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppL10n
/// returned by `AppL10n.of(context)`.
///
/// Applications need to include `AppL10n.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppL10n.localizationsDelegates,
///   supportedLocales: AppL10n.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppL10n.supportedLocales
/// property.
abstract class AppL10n {
  AppL10n(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppL10n of(BuildContext context) {
    return Localizations.of<AppL10n>(context, AppL10n)!;
  }

  static const LocalizationsDelegate<AppL10n> delegate = _AppL10nDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('fr')];

  /// No description provided for @appTitle.
  ///
  /// In fr, this message translates to:
  /// **'CoParentPlanner'**
  String get appTitle;

  /// No description provided for @actionContinue.
  ///
  /// In fr, this message translates to:
  /// **'Continuer'**
  String get actionContinue;

  /// No description provided for @actionCancel.
  ///
  /// In fr, this message translates to:
  /// **'Annuler'**
  String get actionCancel;

  /// No description provided for @actionRetry.
  ///
  /// In fr, this message translates to:
  /// **'Reessayer'**
  String get actionRetry;

  /// No description provided for @actionSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get actionSave;

  /// No description provided for @genericError.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue. Veuillez reessayer.'**
  String get genericError;

  /// No description provided for @fieldRequired.
  ///
  /// In fr, this message translates to:
  /// **'Ce champ est requis.'**
  String get fieldRequired;

  /// No description provided for @emailLabel.
  ///
  /// In fr, this message translates to:
  /// **'Adresse e-mail'**
  String get emailLabel;

  /// No description provided for @passwordLabel.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe'**
  String get passwordLabel;

  /// No description provided for @invalidEmail.
  ///
  /// In fr, this message translates to:
  /// **'Adresse e-mail invalide.'**
  String get invalidEmail;

  /// No description provided for @loginTitle.
  ///
  /// In fr, this message translates to:
  /// **'Connexion'**
  String get loginTitle;

  /// No description provided for @loginSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Accedez a l\'espace de votre famille.'**
  String get loginSubtitle;

  /// No description provided for @loginAction.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get loginAction;

  /// No description provided for @loginForgot.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe oublie ?'**
  String get loginForgot;

  /// No description provided for @loginNoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Pas encore de compte ?'**
  String get loginNoAccount;

  /// No description provided for @loginRegisterLink.
  ///
  /// In fr, this message translates to:
  /// **'Creer un compte'**
  String get loginRegisterLink;

  /// No description provided for @registerTitle.
  ///
  /// In fr, this message translates to:
  /// **'Creer un compte'**
  String get registerTitle;

  /// No description provided for @registerSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Quelques informations pour demarrer.'**
  String get registerSubtitle;

  /// No description provided for @registerFullName.
  ///
  /// In fr, this message translates to:
  /// **'Nom complet (optionnel)'**
  String get registerFullName;

  /// No description provided for @registerPhone.
  ///
  /// In fr, this message translates to:
  /// **'Telephone (optionnel)'**
  String get registerPhone;

  /// No description provided for @registerAction.
  ///
  /// In fr, this message translates to:
  /// **'Creer mon compte'**
  String get registerAction;

  /// No description provided for @registerHasAccount.
  ///
  /// In fr, this message translates to:
  /// **'Deja un compte ?'**
  String get registerHasAccount;

  /// No description provided for @registerLoginLink.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get registerLoginLink;

  /// No description provided for @passwordPolicyHint.
  ///
  /// In fr, this message translates to:
  /// **'Au moins 12 caracteres.'**
  String get passwordPolicyHint;

  /// No description provided for @passwordTooShort.
  ///
  /// In fr, this message translates to:
  /// **'Le mot de passe doit comporter au moins 12 caracteres.'**
  String get passwordTooShort;

  /// No description provided for @checkEmailTitle.
  ///
  /// In fr, this message translates to:
  /// **'Verifiez votre e-mail'**
  String get checkEmailTitle;

  /// No description provided for @checkEmailBody.
  ///
  /// In fr, this message translates to:
  /// **'Un e-mail de verification a ete envoye a {email}. Ouvrez le lien qu\'il contient pour activer votre compte.'**
  String checkEmailBody(String email);

  /// No description provided for @checkEmailBackToLogin.
  ///
  /// In fr, this message translates to:
  /// **'Revenir a la connexion'**
  String get checkEmailBackToLogin;

  /// No description provided for @forgotTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe oublie'**
  String get forgotTitle;

  /// No description provided for @forgotSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre e-mail pour recevoir un lien de reinitialisation.'**
  String get forgotSubtitle;

  /// No description provided for @forgotAction.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer le lien'**
  String get forgotAction;

  /// No description provided for @forgotGenericSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Si un compte existe pour cette adresse, un e-mail de reinitialisation vient d\'etre envoye.'**
  String get forgotGenericSuccess;

  /// No description provided for @resetTitle.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau mot de passe'**
  String get resetTitle;

  /// No description provided for @resetSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez un nouveau mot de passe.'**
  String get resetSubtitle;

  /// No description provided for @resetNewPassword.
  ///
  /// In fr, this message translates to:
  /// **'Nouveau mot de passe'**
  String get resetNewPassword;

  /// No description provided for @resetConfirmPassword.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer le mot de passe'**
  String get resetConfirmPassword;

  /// No description provided for @resetAction.
  ///
  /// In fr, this message translates to:
  /// **'Reinitialiser'**
  String get resetAction;

  /// No description provided for @resetPasswordsMismatch.
  ///
  /// In fr, this message translates to:
  /// **'Les mots de passe ne correspondent pas.'**
  String get resetPasswordsMismatch;

  /// No description provided for @resetSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Votre mot de passe a ete reinitialise. Vous pouvez vous connecter.'**
  String get resetSuccess;

  /// No description provided for @verifyInProgress.
  ///
  /// In fr, this message translates to:
  /// **'Verification en cours...'**
  String get verifyInProgress;

  /// No description provided for @verifySuccess.
  ///
  /// In fr, this message translates to:
  /// **'Votre adresse e-mail est verifiee. Vous pouvez vous connecter.'**
  String get verifySuccess;

  /// No description provided for @verifyError.
  ///
  /// In fr, this message translates to:
  /// **'Lien invalide ou expire.'**
  String get verifyError;

  /// No description provided for @verifyGoToLogin.
  ///
  /// In fr, this message translates to:
  /// **'Aller a la connexion'**
  String get verifyGoToLogin;

  /// No description provided for @emailNotVerified.
  ///
  /// In fr, this message translates to:
  /// **'Veuillez verifier votre adresse e-mail avant de vous connecter.'**
  String get emailNotVerified;

  /// No description provided for @sessionExpired.
  ///
  /// In fr, this message translates to:
  /// **'Session expiree, veuillez vous reconnecter.'**
  String get sessionExpired;

  /// No description provided for @onboardingTitle.
  ///
  /// In fr, this message translates to:
  /// **'Bienvenue'**
  String get onboardingTitle;

  /// No description provided for @onboardingSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Creez votre premiere famille ou rejoignez-en une via une invitation.'**
  String get onboardingSubtitle;

  /// No description provided for @onboardingCreate.
  ///
  /// In fr, this message translates to:
  /// **'Creer une famille'**
  String get onboardingCreate;

  /// No description provided for @onboardingJoin.
  ///
  /// In fr, this message translates to:
  /// **'Rejoindre via une invitation'**
  String get onboardingJoin;

  /// No description provided for @createFamilyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Creer une famille'**
  String get createFamilyTitle;

  /// No description provided for @familyNameLabel.
  ///
  /// In fr, this message translates to:
  /// **'Nom de la famille'**
  String get familyNameLabel;

  /// No description provided for @familyTypeLabel.
  ///
  /// In fr, this message translates to:
  /// **'Type de famille'**
  String get familyTypeLabel;

  /// No description provided for @familyTypeSeparated.
  ///
  /// In fr, this message translates to:
  /// **'Parents separes'**
  String get familyTypeSeparated;

  /// No description provided for @familyTypeIntact.
  ///
  /// In fr, this message translates to:
  /// **'Famille unie'**
  String get familyTypeIntact;

  /// No description provided for @createFamilyAction.
  ///
  /// In fr, this message translates to:
  /// **'Creer la famille'**
  String get createFamilyAction;

  /// No description provided for @joinFamilyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Rejoindre une famille'**
  String get joinFamilyTitle;

  /// No description provided for @joinFamilySubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez le code d\'invitation recu.'**
  String get joinFamilySubtitle;

  /// No description provided for @invitationTokenLabel.
  ///
  /// In fr, this message translates to:
  /// **'Code d\'invitation'**
  String get invitationTokenLabel;

  /// No description provided for @joinFamilyAction.
  ///
  /// In fr, this message translates to:
  /// **'Rejoindre'**
  String get joinFamilyAction;

  /// No description provided for @tabHome.
  ///
  /// In fr, this message translates to:
  /// **'Accueil'**
  String get tabHome;

  /// No description provided for @tabCustody.
  ///
  /// In fr, this message translates to:
  /// **'Garde'**
  String get tabCustody;

  /// No description provided for @tabAgenda.
  ///
  /// In fr, this message translates to:
  /// **'Agenda'**
  String get tabAgenda;

  /// No description provided for @tabNotes.
  ///
  /// In fr, this message translates to:
  /// **'Enfants'**
  String get tabNotes;

  /// No description provided for @tabSettings.
  ///
  /// In fr, this message translates to:
  /// **'Reglages'**
  String get tabSettings;

  /// No description provided for @homeGreeting.
  ///
  /// In fr, this message translates to:
  /// **'Bonjour'**
  String get homeGreeting;

  /// No description provided for @homeAllAgenda.
  ///
  /// In fr, this message translates to:
  /// **'Tout l\'agenda'**
  String get homeAllAgenda;

  /// No description provided for @homeUpcomingTitle.
  ///
  /// In fr, this message translates to:
  /// **'A venir'**
  String get homeUpcomingTitle;

  /// No description provided for @homeUpcomingEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun evenement a venir.'**
  String get homeUpcomingEmpty;

  /// No description provided for @homeDecisionsTitle.
  ///
  /// In fr, this message translates to:
  /// **'A decider'**
  String get homeDecisionsTitle;

  /// No description provided for @homeDecisionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Rien a decider pour le moment.'**
  String get homeDecisionsEmpty;

  /// No description provided for @homeNotesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Dernieres notes'**
  String get homeNotesTitle;

  /// No description provided for @homeNotesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune note pour le moment.'**
  String get homeNotesEmpty;

  /// No description provided for @homeCustodyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Garde de la semaine'**
  String get homeCustodyTitle;

  /// No description provided for @homeCustodyEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun planning de garde pour le moment.'**
  String get homeCustodyEmpty;

  /// No description provided for @homeFamilyIntact.
  ///
  /// In fr, this message translates to:
  /// **'Toute la famille reunie.'**
  String get homeFamilyIntact;

  /// No description provided for @settingsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Reglages'**
  String get settingsTitle;

  /// No description provided for @settingsProfile.
  ///
  /// In fr, this message translates to:
  /// **'Profil'**
  String get settingsProfile;

  /// No description provided for @settingsFullName.
  ///
  /// In fr, this message translates to:
  /// **'Nom complet'**
  String get settingsFullName;

  /// No description provided for @settingsPhone.
  ///
  /// In fr, this message translates to:
  /// **'Telephone'**
  String get settingsPhone;

  /// No description provided for @settingsLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get settingsLanguage;

  /// No description provided for @settingsLogout.
  ///
  /// In fr, this message translates to:
  /// **'Se deconnecter'**
  String get settingsLogout;

  /// No description provided for @settingsProfileUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Profil mis a jour.'**
  String get settingsProfileUpdated;

  /// No description provided for @settingsActiveFamily.
  ///
  /// In fr, this message translates to:
  /// **'Famille active'**
  String get settingsActiveFamily;

  /// No description provided for @settingsSwitchFamily.
  ///
  /// In fr, this message translates to:
  /// **'Changer de famille'**
  String get settingsSwitchFamily;

  /// No description provided for @settingsMembers.
  ///
  /// In fr, this message translates to:
  /// **'Membres de la famille'**
  String get settingsMembers;

  /// No description provided for @membersTitle.
  ///
  /// In fr, this message translates to:
  /// **'Membres'**
  String get membersTitle;

  /// No description provided for @membersEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun membre.'**
  String get membersEmpty;

  /// No description provided for @membersRoleParent.
  ///
  /// In fr, this message translates to:
  /// **'Parent'**
  String get membersRoleParent;

  /// No description provided for @membersRoleStepParent.
  ///
  /// In fr, this message translates to:
  /// **'Beau-parent'**
  String get membersRoleStepParent;

  /// No description provided for @membersRoleChild.
  ///
  /// In fr, this message translates to:
  /// **'Enfant'**
  String get membersRoleChild;

  /// No description provided for @membersAdminBadge.
  ///
  /// In fr, this message translates to:
  /// **'admin'**
  String get membersAdminBadge;

  /// No description provided for @membersInvite.
  ///
  /// In fr, this message translates to:
  /// **'Inviter une personne'**
  String get membersInvite;

  /// No description provided for @membersPending.
  ///
  /// In fr, this message translates to:
  /// **'Invitations en attente'**
  String get membersPending;

  /// No description provided for @membersRevoke.
  ///
  /// In fr, this message translates to:
  /// **'Revoquer'**
  String get membersRevoke;

  /// No description provided for @membersInvited.
  ///
  /// In fr, this message translates to:
  /// **'Invitation envoyee.'**
  String get membersInvited;

  /// No description provided for @membersRevoked.
  ///
  /// In fr, this message translates to:
  /// **'Invitation revoquee.'**
  String get membersRevoked;

  /// No description provided for @inviteTitle.
  ///
  /// In fr, this message translates to:
  /// **'Inviter une personne'**
  String get inviteTitle;

  /// No description provided for @inviteEmail.
  ///
  /// In fr, this message translates to:
  /// **'Adresse e-mail'**
  String get inviteEmail;

  /// No description provided for @inviteRole.
  ///
  /// In fr, this message translates to:
  /// **'Role'**
  String get inviteRole;

  /// No description provided for @inviteSend.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer l\'invitation'**
  String get inviteSend;

  /// No description provided for @inviteRightAgenda.
  ///
  /// In fr, this message translates to:
  /// **'Agenda'**
  String get inviteRightAgenda;

  /// No description provided for @inviteRightCustody.
  ///
  /// In fr, this message translates to:
  /// **'Garde'**
  String get inviteRightCustody;

  /// No description provided for @inviteRightNotes.
  ///
  /// In fr, this message translates to:
  /// **'Notes enfants'**
  String get inviteRightNotes;

  /// No description provided for @inviteRightExchanges.
  ///
  /// In fr, this message translates to:
  /// **'Echanges'**
  String get inviteRightExchanges;

  /// No description provided for @rightNone.
  ///
  /// In fr, this message translates to:
  /// **'Aucun'**
  String get rightNone;

  /// No description provided for @rightView.
  ///
  /// In fr, this message translates to:
  /// **'Lecture'**
  String get rightView;

  /// No description provided for @rightEdit.
  ///
  /// In fr, this message translates to:
  /// **'Edition'**
  String get rightEdit;

  /// No description provided for @settingsChildren.
  ///
  /// In fr, this message translates to:
  /// **'Enfants'**
  String get settingsChildren;

  /// No description provided for @childrenTitle.
  ///
  /// In fr, this message translates to:
  /// **'Enfants'**
  String get childrenTitle;

  /// No description provided for @childrenEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun enfant pour le moment.'**
  String get childrenEmpty;

  /// No description provided for @childAge.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{Moins d\'un an} one{{count} an} other{{count} ans}}'**
  String childAge(num count);

  /// No description provided for @childInfoTitle.
  ///
  /// In fr, this message translates to:
  /// **'Informations'**
  String get childInfoTitle;

  /// No description provided for @childSchool.
  ///
  /// In fr, this message translates to:
  /// **'Scolarité'**
  String get childSchool;

  /// No description provided for @childNotesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Notes'**
  String get childNotesTitle;

  /// No description provided for @childNotesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune note.'**
  String get childNotesEmpty;

  /// No description provided for @childAddNote.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une note'**
  String get childAddNote;

  /// No description provided for @childNotePlaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Saisir une note…'**
  String get childNotePlaceholder;

  /// No description provided for @childNoteSend.
  ///
  /// In fr, this message translates to:
  /// **'Publier'**
  String get childNoteSend;

  /// No description provided for @childHealthWarning.
  ///
  /// In fr, this message translates to:
  /// **'Ne saisissez pas de données de santé (allergies, traitements, diagnostics). Informations pratiques générales uniquement.'**
  String get childHealthWarning;

  /// No description provided for @custodyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Garde'**
  String get custodyTitle;

  /// No description provided for @custodyScreenTitle.
  ///
  /// In fr, this message translates to:
  /// **'Planning de garde'**
  String get custodyScreenTitle;

  /// No description provided for @custodyRuleHandover.
  ///
  /// In fr, this message translates to:
  /// **'Échange le {weekday} à {time}'**
  String custodyRuleHandover(String weekday, String time);

  /// No description provided for @custodyActivity.
  ///
  /// In fr, this message translates to:
  /// **'Activité'**
  String get custodyActivity;

  /// No description provided for @custodyOpen.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir le planning'**
  String get custodyOpen;

  /// No description provided for @custodyIntact.
  ///
  /// In fr, this message translates to:
  /// **'La garde ne s\'applique qu\'aux familles séparées.'**
  String get custodyIntact;

  /// No description provided for @custodyNoPattern.
  ///
  /// In fr, this message translates to:
  /// **'Aucun rythme de garde configuré.'**
  String get custodyNoPattern;

  /// No description provided for @custodyConfigure.
  ///
  /// In fr, this message translates to:
  /// **'Configurer le rythme'**
  String get custodyConfigure;

  /// No description provided for @custodyParentA.
  ///
  /// In fr, this message translates to:
  /// **'Parent A'**
  String get custodyParentA;

  /// No description provided for @custodyParentB.
  ///
  /// In fr, this message translates to:
  /// **'Parent B'**
  String get custodyParentB;

  /// No description provided for @custodyExceptional.
  ///
  /// In fr, this message translates to:
  /// **'Exceptionnel'**
  String get custodyExceptional;

  /// No description provided for @custodyHoliday.
  ///
  /// In fr, this message translates to:
  /// **'Vacances'**
  String get custodyHoliday;

  /// No description provided for @custodyStatsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Statistiques du mois'**
  String get custodyStatsTitle;

  /// No description provided for @custodyStatsDays.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{aucun jour} one{{count} jour} other{{count} jours}}'**
  String custodyStatsDays(num count);

  /// No description provided for @custodyDayDetailTitle.
  ///
  /// In fr, this message translates to:
  /// **'Détail du jour'**
  String get custodyDayDetailTitle;

  /// No description provided for @custodySource.
  ///
  /// In fr, this message translates to:
  /// **'Origine'**
  String get custodySource;

  /// No description provided for @custodySourceBase.
  ///
  /// In fr, this message translates to:
  /// **'Rythme de base'**
  String get custodySourceBase;

  /// No description provided for @custodySourceHoliday.
  ///
  /// In fr, this message translates to:
  /// **'Vacances'**
  String get custodySourceHoliday;

  /// No description provided for @custodySourceException.
  ///
  /// In fr, this message translates to:
  /// **'Exception'**
  String get custodySourceException;

  /// No description provided for @custodySwapDay.
  ///
  /// In fr, this message translates to:
  /// **'Échanger ce jour'**
  String get custodySwapDay;

  /// No description provided for @custodyAssignedTo.
  ///
  /// In fr, this message translates to:
  /// **'Confié à'**
  String get custodyAssignedTo;

  /// No description provided for @custodyNoParent.
  ///
  /// In fr, this message translates to:
  /// **'Non attribué'**
  String get custodyNoParent;

  /// No description provided for @custodySwapped.
  ///
  /// In fr, this message translates to:
  /// **'Jour échangé.'**
  String get custodySwapped;

  /// No description provided for @custodySwapUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Échange impossible : l\'autre parent n\'est pas défini.'**
  String get custodySwapUnavailable;

  /// No description provided for @custodyParentsRequired.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez deux parents distincts.'**
  String get custodyParentsRequired;

  /// No description provided for @custodyExceptionsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Exceptions'**
  String get custodyExceptionsTitle;

  /// No description provided for @custodyExceptionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune exception.'**
  String get custodyExceptionsEmpty;

  /// No description provided for @custodyAddException.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une exception'**
  String get custodyAddException;

  /// No description provided for @custodyStartDate.
  ///
  /// In fr, this message translates to:
  /// **'Début'**
  String get custodyStartDate;

  /// No description provided for @custodyEndDate.
  ///
  /// In fr, this message translates to:
  /// **'Fin'**
  String get custodyEndDate;

  /// No description provided for @custodyParent.
  ///
  /// In fr, this message translates to:
  /// **'Parent'**
  String get custodyParent;

  /// No description provided for @custodyReason.
  ///
  /// In fr, this message translates to:
  /// **'Motif (optionnel)'**
  String get custodyReason;

  /// No description provided for @custodyDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get custodyDelete;

  /// No description provided for @custodyExceptionAdded.
  ///
  /// In fr, this message translates to:
  /// **'Exception ajoutée.'**
  String get custodyExceptionAdded;

  /// No description provided for @custodyExceptionDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Exception supprimée.'**
  String get custodyExceptionDeleted;

  /// No description provided for @custodyHolidaysTitle.
  ///
  /// In fr, this message translates to:
  /// **'Vacances scolaires'**
  String get custodyHolidaysTitle;

  /// No description provided for @custodyHolidaysEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune période de vacances.'**
  String get custodyHolidaysEmpty;

  /// No description provided for @custodyImportHolidays.
  ///
  /// In fr, this message translates to:
  /// **'Importer les vacances officielles'**
  String get custodyImportHolidays;

  /// No description provided for @custodyHolidaysImported.
  ///
  /// In fr, this message translates to:
  /// **'Import des vacances lancé.'**
  String get custodyHolidaysImported;

  /// No description provided for @custodySettingsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Rythme de garde'**
  String get custodySettingsTitle;

  /// No description provided for @custodyKind.
  ///
  /// In fr, this message translates to:
  /// **'Modèle'**
  String get custodyKind;

  /// No description provided for @custodyKindAlternating.
  ///
  /// In fr, this message translates to:
  /// **'Une semaine sur deux'**
  String get custodyKindAlternating;

  /// No description provided for @custodyKind223.
  ///
  /// In fr, this message translates to:
  /// **'2-2-3'**
  String get custodyKind223;

  /// No description provided for @custodyKindCustom.
  ///
  /// In fr, this message translates to:
  /// **'Personnalisé (14 jours)'**
  String get custodyKindCustom;

  /// No description provided for @custodyAnchorDate.
  ///
  /// In fr, this message translates to:
  /// **'Date d\'ancrage'**
  String get custodyAnchorDate;

  /// No description provided for @custodyHandoverWeekday.
  ///
  /// In fr, this message translates to:
  /// **'Jour d\'échange'**
  String get custodyHandoverWeekday;

  /// No description provided for @custodyHandoverTime.
  ///
  /// In fr, this message translates to:
  /// **'Heure d\'échange'**
  String get custodyHandoverTime;

  /// No description provided for @custodyCustomPattern.
  ///
  /// In fr, this message translates to:
  /// **'Motif personnalisé (14 nuits)'**
  String get custodyCustomPattern;

  /// No description provided for @custodySave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer le rythme'**
  String get custodySave;

  /// No description provided for @custodySaved.
  ///
  /// In fr, this message translates to:
  /// **'Rythme enregistré.'**
  String get custodySaved;

  /// No description provided for @custodyPatternSaving.
  ///
  /// In fr, this message translates to:
  /// **'Génération du planning en cours…'**
  String get custodyPatternSaving;

  /// No description provided for @weekdaySunday.
  ///
  /// In fr, this message translates to:
  /// **'Dimanche'**
  String get weekdaySunday;

  /// No description provided for @weekdayMonday.
  ///
  /// In fr, this message translates to:
  /// **'Lundi'**
  String get weekdayMonday;

  /// No description provided for @weekdayTuesday.
  ///
  /// In fr, this message translates to:
  /// **'Mardi'**
  String get weekdayTuesday;

  /// No description provided for @weekdayWednesday.
  ///
  /// In fr, this message translates to:
  /// **'Mercredi'**
  String get weekdayWednesday;

  /// No description provided for @weekdayThursday.
  ///
  /// In fr, this message translates to:
  /// **'Jeudi'**
  String get weekdayThursday;

  /// No description provided for @weekdayFriday.
  ///
  /// In fr, this message translates to:
  /// **'Vendredi'**
  String get weekdayFriday;

  /// No description provided for @weekdaySaturday.
  ///
  /// In fr, this message translates to:
  /// **'Samedi'**
  String get weekdaySaturday;

  /// No description provided for @agendaTitle.
  ///
  /// In fr, this message translates to:
  /// **'Agenda'**
  String get agendaTitle;

  /// No description provided for @agendaEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun événement ce mois-ci.'**
  String get agendaEmpty;

  /// No description provided for @agendaFilterAll.
  ///
  /// In fr, this message translates to:
  /// **'Tout'**
  String get agendaFilterAll;

  /// No description provided for @agendaDayEvents.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{Aucun événement} one{{count} événement} other{{count} événements}}'**
  String agendaDayEvents(num count);

  /// No description provided for @agendaNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouvel événement'**
  String get agendaNew;

  /// No description provided for @agendaCreate.
  ///
  /// In fr, this message translates to:
  /// **'Créer'**
  String get agendaCreate;

  /// No description provided for @agendaCreated.
  ///
  /// In fr, this message translates to:
  /// **'Événement créé.'**
  String get agendaCreated;

  /// No description provided for @agendaFieldTitle.
  ///
  /// In fr, this message translates to:
  /// **'Titre'**
  String get agendaFieldTitle;

  /// No description provided for @agendaCategory.
  ///
  /// In fr, this message translates to:
  /// **'Catégorie'**
  String get agendaCategory;

  /// No description provided for @agendaResponsible.
  ///
  /// In fr, this message translates to:
  /// **'Responsable'**
  String get agendaResponsible;

  /// No description provided for @agendaWholeFamily.
  ///
  /// In fr, this message translates to:
  /// **'Toute la famille'**
  String get agendaWholeFamily;

  /// No description provided for @agendaChildren.
  ///
  /// In fr, this message translates to:
  /// **'Enfant(s)'**
  String get agendaChildren;

  /// No description provided for @agendaAllDay.
  ///
  /// In fr, this message translates to:
  /// **'Journée entière'**
  String get agendaAllDay;

  /// No description provided for @agendaStart.
  ///
  /// In fr, this message translates to:
  /// **'Début'**
  String get agendaStart;

  /// No description provided for @agendaEnd.
  ///
  /// In fr, this message translates to:
  /// **'Fin (optionnelle)'**
  String get agendaEnd;

  /// No description provided for @agendaLocation.
  ///
  /// In fr, this message translates to:
  /// **'Lieu'**
  String get agendaLocation;

  /// No description provided for @agendaAllDayLabel.
  ///
  /// In fr, this message translates to:
  /// **'Journée'**
  String get agendaAllDayLabel;

  /// No description provided for @agendaUpcomingSee.
  ///
  /// In fr, this message translates to:
  /// **'Voir l\'agenda'**
  String get agendaUpcomingSee;

  /// No description provided for @catSport.
  ///
  /// In fr, this message translates to:
  /// **'Sport'**
  String get catSport;

  /// No description provided for @catMusic.
  ///
  /// In fr, this message translates to:
  /// **'Musique'**
  String get catMusic;

  /// No description provided for @catSchool.
  ///
  /// In fr, this message translates to:
  /// **'École'**
  String get catSchool;

  /// No description provided for @catHealth.
  ///
  /// In fr, this message translates to:
  /// **'Santé'**
  String get catHealth;

  /// No description provided for @catOther.
  ///
  /// In fr, this message translates to:
  /// **'Autre'**
  String get catOther;

  /// No description provided for @overlaySectionTitle.
  ///
  /// In fr, this message translates to:
  /// **'Overlay familles liées'**
  String get overlaySectionTitle;

  /// No description provided for @overlayLinkedFamily.
  ///
  /// In fr, this message translates to:
  /// **'Famille liée'**
  String get overlayLinkedFamily;

  /// No description provided for @overlayToggle.
  ///
  /// In fr, this message translates to:
  /// **'Overlay famille liée · {name}'**
  String overlayToggle(String name);

  /// No description provided for @overlayEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune activité de la famille liée sur cette période.'**
  String get overlayEmpty;
}

class _AppL10nDelegate extends LocalizationsDelegate<AppL10n> {
  const _AppL10nDelegate();

  @override
  Future<AppL10n> load(Locale locale) {
    return SynchronousFuture<AppL10n>(lookupAppL10n(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['fr'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppL10nDelegate old) => false;
}

AppL10n lookupAppL10n(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'fr':
      return AppL10nFr();
  }

  throw FlutterError(
    'AppL10n.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
