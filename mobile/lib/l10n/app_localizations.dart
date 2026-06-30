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
