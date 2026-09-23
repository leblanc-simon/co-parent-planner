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
  /// **'Réessayer'**
  String get actionRetry;

  /// No description provided for @actionSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get actionSave;

  /// No description provided for @genericError.
  ///
  /// In fr, this message translates to:
  /// **'Une erreur est survenue. Veuillez réessayer.'**
  String get genericError;

  /// No description provided for @networkUnreachable.
  ///
  /// In fr, this message translates to:
  /// **'Connexion impossible. Vérifiez votre réseau.'**
  String get networkUnreachable;

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
  /// **'Accédez à l\'espace de votre famille.'**
  String get loginSubtitle;

  /// No description provided for @loginAction.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get loginAction;

  /// No description provided for @loginForgot.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe oublié ?'**
  String get loginForgot;

  /// No description provided for @loginNoAccount.
  ///
  /// In fr, this message translates to:
  /// **'Pas encore de compte ?'**
  String get loginNoAccount;

  /// No description provided for @loginRegisterLink.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get loginRegisterLink;

  /// No description provided for @registerTitle.
  ///
  /// In fr, this message translates to:
  /// **'Créer un compte'**
  String get registerTitle;

  /// No description provided for @registerSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Quelques informations pour démarrer.'**
  String get registerSubtitle;

  /// No description provided for @registerFullName.
  ///
  /// In fr, this message translates to:
  /// **'Nom complet (optionnel)'**
  String get registerFullName;

  /// No description provided for @registerPhone.
  ///
  /// In fr, this message translates to:
  /// **'Téléphone (optionnel)'**
  String get registerPhone;

  /// No description provided for @registerAction.
  ///
  /// In fr, this message translates to:
  /// **'Créer mon compte'**
  String get registerAction;

  /// No description provided for @registerHasAccount.
  ///
  /// In fr, this message translates to:
  /// **'Déjà un compte ?'**
  String get registerHasAccount;

  /// No description provided for @registerLoginLink.
  ///
  /// In fr, this message translates to:
  /// **'Se connecter'**
  String get registerLoginLink;

  /// No description provided for @passwordPolicyHint.
  ///
  /// In fr, this message translates to:
  /// **'Au moins 12 caractères.'**
  String get passwordPolicyHint;

  /// No description provided for @passwordTooShort.
  ///
  /// In fr, this message translates to:
  /// **'Le mot de passe doit comporter au moins 12 caractères.'**
  String get passwordTooShort;

  /// No description provided for @checkEmailTitle.
  ///
  /// In fr, this message translates to:
  /// **'Vérifiez votre e-mail'**
  String get checkEmailTitle;

  /// No description provided for @checkEmailBody.
  ///
  /// In fr, this message translates to:
  /// **'Un e-mail de vérification a été envoyé à {email}. Ouvrez le lien qu\'il contient pour activer votre compte.'**
  String checkEmailBody(String email);

  /// No description provided for @checkEmailBackToLogin.
  ///
  /// In fr, this message translates to:
  /// **'Revenir à la connexion'**
  String get checkEmailBackToLogin;

  /// No description provided for @forgotTitle.
  ///
  /// In fr, this message translates to:
  /// **'Mot de passe oublié'**
  String get forgotTitle;

  /// No description provided for @forgotSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez votre e-mail pour recevoir un lien de réinitialisation.'**
  String get forgotSubtitle;

  /// No description provided for @forgotAction.
  ///
  /// In fr, this message translates to:
  /// **'Envoyer le lien'**
  String get forgotAction;

  /// No description provided for @forgotGenericSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Si un compte existe pour cette adresse, un e-mail de réinitialisation vient d\'être envoyé.'**
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
  /// **'Réinitialiser'**
  String get resetAction;

  /// No description provided for @resetPasswordsMismatch.
  ///
  /// In fr, this message translates to:
  /// **'Les mots de passe ne correspondent pas.'**
  String get resetPasswordsMismatch;

  /// No description provided for @resetSuccess.
  ///
  /// In fr, this message translates to:
  /// **'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.'**
  String get resetSuccess;

  /// No description provided for @verifyInProgress.
  ///
  /// In fr, this message translates to:
  /// **'Vérification en cours…'**
  String get verifyInProgress;

  /// No description provided for @verifySuccess.
  ///
  /// In fr, this message translates to:
  /// **'Votre adresse e-mail est vérifiée. Vous pouvez vous connecter.'**
  String get verifySuccess;

  /// No description provided for @verifyError.
  ///
  /// In fr, this message translates to:
  /// **'Lien invalide ou expiré.'**
  String get verifyError;

  /// No description provided for @verifyGoToLogin.
  ///
  /// In fr, this message translates to:
  /// **'Aller à la connexion'**
  String get verifyGoToLogin;

  /// No description provided for @emailNotVerified.
  ///
  /// In fr, this message translates to:
  /// **'Veuillez vérifier votre adresse e-mail avant de vous connecter.'**
  String get emailNotVerified;

  /// No description provided for @sessionExpired.
  ///
  /// In fr, this message translates to:
  /// **'Session expirée, veuillez vous reconnecter.'**
  String get sessionExpired;

  /// No description provided for @onboardingTitle.
  ///
  /// In fr, this message translates to:
  /// **'Bienvenue'**
  String get onboardingTitle;

  /// No description provided for @onboardingSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Créez votre première famille ou rejoignez-en une via une invitation.'**
  String get onboardingSubtitle;

  /// No description provided for @onboardingCreate.
  ///
  /// In fr, this message translates to:
  /// **'Créer une famille'**
  String get onboardingCreate;

  /// No description provided for @onboardingJoin.
  ///
  /// In fr, this message translates to:
  /// **'Rejoindre via une invitation'**
  String get onboardingJoin;

  /// No description provided for @onboardingPickExisting.
  ///
  /// In fr, this message translates to:
  /// **'Reprendre une famille existante'**
  String get onboardingPickExisting;

  /// No description provided for @onboardingSubtitleWithFamilies.
  ///
  /// In fr, this message translates to:
  /// **'Choisissez la famille à ouvrir, ou créez-en une nouvelle.'**
  String get onboardingSubtitleWithFamilies;

  /// No description provided for @createFamilyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Créer une famille'**
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
  /// **'Parents séparés'**
  String get familyTypeSeparated;

  /// No description provided for @familyTypeIntact.
  ///
  /// In fr, this message translates to:
  /// **'Famille unie'**
  String get familyTypeIntact;

  /// No description provided for @createFamilyAction.
  ///
  /// In fr, this message translates to:
  /// **'Créer la famille'**
  String get createFamilyAction;

  /// No description provided for @joinFamilyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Rejoindre une famille'**
  String get joinFamilyTitle;

  /// No description provided for @joinFamilySubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez le code d\'invitation reçu.'**
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
  /// **'Réglages'**
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
  /// **'À venir'**
  String get homeUpcomingTitle;

  /// No description provided for @homeUpcomingEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun événement à venir.'**
  String get homeUpcomingEmpty;

  /// No description provided for @homeDecisionsTitle.
  ///
  /// In fr, this message translates to:
  /// **'À décider'**
  String get homeDecisionsTitle;

  /// No description provided for @homeDecisionsEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Rien à décider pour le moment.'**
  String get homeDecisionsEmpty;

  /// No description provided for @homeNotesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Dernières notes'**
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

  /// No description provided for @homeCustodyWithYou.
  ///
  /// In fr, this message translates to:
  /// **'Les enfants sont avec vous cette semaine.'**
  String get homeCustodyWithYou;

  /// No description provided for @homeCustodyWithParent.
  ///
  /// In fr, this message translates to:
  /// **'Les enfants sont chez {parent} cette semaine.'**
  String homeCustodyWithParent(String parent);

  /// No description provided for @homeFamilyIntact.
  ///
  /// In fr, this message translates to:
  /// **'Toute la famille réunie.'**
  String get homeFamilyIntact;

  /// No description provided for @settingsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Réglages'**
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
  /// **'Téléphone'**
  String get settingsPhone;

  /// No description provided for @settingsLanguage.
  ///
  /// In fr, this message translates to:
  /// **'Langue'**
  String get settingsLanguage;

  /// No description provided for @settingsLanguageValue.
  ///
  /// In fr, this message translates to:
  /// **'Français (fr-FR)'**
  String get settingsLanguageValue;

  /// No description provided for @settingsLogout.
  ///
  /// In fr, this message translates to:
  /// **'Se déconnecter'**
  String get settingsLogout;

  /// No description provided for @settingsProfileUpdated.
  ///
  /// In fr, this message translates to:
  /// **'Profil mis à jour.'**
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

  /// No description provided for @membersPartnerOf.
  ///
  /// In fr, this message translates to:
  /// **'Conjoint·e de {name}'**
  String membersPartnerOf(String name);

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
  /// **'Révoquer'**
  String get membersRevoke;

  /// No description provided for @membersInvited.
  ///
  /// In fr, this message translates to:
  /// **'Invitation envoyée.'**
  String get membersInvited;

  /// No description provided for @membersRevokeConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Révoquer cette invitation ?'**
  String get membersRevokeConfirmTitle;

  /// No description provided for @membersRevokeConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'L\'invitation envoyée à {email} ne sera plus valable. Vous pourrez réinviter cette adresse.'**
  String membersRevokeConfirmBody(String email);

  /// No description provided for @membersRevoked.
  ///
  /// In fr, this message translates to:
  /// **'Invitation révoquée.'**
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
  /// **'Rôle'**
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

  /// No description provided for @inviteChildNoEdit.
  ///
  /// In fr, this message translates to:
  /// **'Un compte enfant ne peut jamais modifier : ses droits sont limités à la consultation.'**
  String get inviteChildNoEdit;

  /// No description provided for @inviteRightExchanges.
  ///
  /// In fr, this message translates to:
  /// **'Échanges'**
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
  /// **'Édition'**
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

  /// No description provided for @custodyPrevMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois précédent'**
  String get custodyPrevMonth;

  /// No description provided for @custodyNextMonth.
  ///
  /// In fr, this message translates to:
  /// **'Mois suivant'**
  String get custodyNextMonth;

  /// No description provided for @custodyToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get custodyToday;

  /// No description provided for @custodyKindMidweek.
  ///
  /// In fr, this message translates to:
  /// **'Milieu de semaine + week-ends'**
  String get custodyKindMidweek;

  /// No description provided for @custodyKindUnknown.
  ///
  /// In fr, this message translates to:
  /// **'Rythme de garde'**
  String get custodyKindUnknown;

  /// No description provided for @custodyDaySemantics.
  ///
  /// In fr, this message translates to:
  /// **'{date} — chez {parent}'**
  String custodyDaySemantics(String date, String parent);

  /// Annonce d'une case de calendrier une journée partagée : les tranches, dans l'ordre.
  ///
  /// In fr, this message translates to:
  /// **'{date} — {slices}'**
  String custodyDaySplitSemantics(String date, String slices);

  /// Une tranche horaire d'une journée partagée : chez qui, de quand à quand.
  ///
  /// In fr, this message translates to:
  /// **'chez {parent} de {from} à {to}'**
  String custodyDaySlice(String parent, String from, String to);

  /// Entrée de légende expliquant le filet des cases partagées.
  ///
  /// In fr, this message translates to:
  /// **'Journée partagée : le filet marque l\'heure du passage d\'un parent à l\'autre'**
  String get custodySharedDay;

  /// Fiche de jour : répartition d'une journée partagée, tranche par tranche.
  ///
  /// In fr, this message translates to:
  /// **'Journée partagée · {slices}'**
  String custodySharedDayDetail(String slices);

  /// No description provided for @custodyColorsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Couleurs du planning'**
  String get custodyColorsTitle;

  /// No description provided for @custodyColorsHint.
  ///
  /// In fr, this message translates to:
  /// **'Ces teintes ne changent que votre affichage : l\'autre parent garde les siennes. L\'initiale de chaque parent reste affichée sur les cases.'**
  String get custodyColorsHint;

  /// No description provided for @custodyColorsSaved.
  ///
  /// In fr, this message translates to:
  /// **'Couleurs du planning enregistrées.'**
  String get custodyColorsSaved;

  /// Étiquette accessible du groupe de teintes d'un parent (cdc §8.2).
  ///
  /// In fr, this message translates to:
  /// **'Couleur de {parent}'**
  String custodyColorFor(String parent);

  /// No description provided for @inviteCopyRightsFrom.
  ///
  /// In fr, this message translates to:
  /// **'Reprendre les droits de'**
  String get inviteCopyRightsFrom;

  /// No description provided for @inviteCopyRightsNone.
  ///
  /// In fr, this message translates to:
  /// **'— partir de zéro —'**
  String get inviteCopyRightsNone;

  /// No description provided for @custodyAtParent.
  ///
  /// In fr, this message translates to:
  /// **'Chez'**
  String get custodyAtParent;

  /// Foyer (E2) : beau-parent associé au parent qui a la garde ce jour-là.
  ///
  /// In fr, this message translates to:
  /// **'avec {partner}'**
  String custodyWithPartner(String partner);

  /// No description provided for @custodyHandover.
  ///
  /// In fr, this message translates to:
  /// **'Retour chez {parent} le {date} à {time}'**
  String custodyHandover(String parent, String date, String time);

  /// No description provided for @custodyDayUnassigned.
  ///
  /// In fr, this message translates to:
  /// **'Ce jour n\'est pas encore attribué.'**
  String get custodyDayUnassigned;

  /// No description provided for @custodyDayUnplanned.
  ///
  /// In fr, this message translates to:
  /// **'Jour non planifié — hors de la période générée'**
  String get custodyDayUnplanned;

  /// No description provided for @custodyRuleHabitual.
  ///
  /// In fr, this message translates to:
  /// **'Rythme habituel'**
  String get custodyRuleHabitual;

  /// No description provided for @custodyRuleDuring.
  ///
  /// In fr, this message translates to:
  /// **'pendant {label}'**
  String custodyRuleDuring(String label);

  /// No description provided for @custodyDayActivities.
  ///
  /// In fr, this message translates to:
  /// **'Activités du jour'**
  String get custodyDayActivities;

  /// No description provided for @custodyDayActivitiesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune activité ce jour.'**
  String get custodyDayActivitiesEmpty;

  /// No description provided for @custodyAllDay.
  ///
  /// In fr, this message translates to:
  /// **'Journée'**
  String get custodyAllDay;

  /// No description provided for @custodyEditPattern.
  ///
  /// In fr, this message translates to:
  /// **'Modifier la garde'**
  String get custodyEditPattern;

  /// No description provided for @custodyPastNoSwap.
  ///
  /// In fr, this message translates to:
  /// **'Un jour passé ne peut plus être échangé'**
  String get custodyPastNoSwap;

  /// No description provided for @custodySwapReason.
  ///
  /// In fr, this message translates to:
  /// **'Échange'**
  String get custodySwapReason;

  /// No description provided for @custodyHolidayNoRule.
  ///
  /// In fr, this message translates to:
  /// **'Aucune règle de partage — le rythme habituel s\'applique.'**
  String get custodyHolidayNoRule;

  /// No description provided for @holidayRuleAlternatingWeeks.
  ///
  /// In fr, this message translates to:
  /// **'Une semaine sur deux'**
  String get holidayRuleAlternatingWeeks;

  /// No description provided for @holidayRuleSplit5050.
  ///
  /// In fr, this message translates to:
  /// **'Moitié / moitié'**
  String get holidayRuleSplit5050;

  /// No description provided for @holidayRuleFixedParent.
  ///
  /// In fr, this message translates to:
  /// **'Toujours chez le même parent'**
  String get holidayRuleFixedParent;

  /// No description provided for @holidayRuleEveAlternating.
  ///
  /// In fr, this message translates to:
  /// **'Réveillons en alternance'**
  String get holidayRuleEveAlternating;

  /// No description provided for @holidayRuleCustom.
  ///
  /// In fr, this message translates to:
  /// **'Répartition personnalisée'**
  String get holidayRuleCustom;

  /// No description provided for @custodyMidweekTitle.
  ///
  /// In fr, this message translates to:
  /// **'Jours de la semaine'**
  String get custodyMidweekTitle;

  /// No description provided for @custodyMidweekHint.
  ///
  /// In fr, this message translates to:
  /// **'Chaque jour de semaine reste chez le même parent. Touchez un jour pour basculer parent A → parent B → week-end alterné.'**
  String get custodyMidweekHint;

  /// No description provided for @custodyWeekend.
  ///
  /// In fr, this message translates to:
  /// **'Week-end'**
  String get custodyWeekend;

  /// No description provided for @custodyWeekendStart.
  ///
  /// In fr, this message translates to:
  /// **'Le week-end commence chez'**
  String get custodyWeekendStart;

  /// No description provided for @custodyMidweekInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Gardez au moins un jour de week-end et au plus trois.'**
  String get custodyMidweekInvalid;

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

  /// No description provided for @custodyRevertSwap.
  ///
  /// In fr, this message translates to:
  /// **'Annuler l\'échange'**
  String get custodyRevertSwap;

  /// Feuille d'échange : rappelle à qui la journée est confiée.
  ///
  /// In fr, this message translates to:
  /// **'Confier ce jour à {parent}, sur le créneau de votre choix.'**
  String custodySwapIntro(String parent);

  /// No description provided for @custodyDayStartTime.
  ///
  /// In fr, this message translates to:
  /// **'Heure de début de journée'**
  String get custodyDayStartTime;

  /// Réglages du rythme : à quoi sert l'heure de début de journée.
  ///
  /// In fr, this message translates to:
  /// **'{time} — proposée lorsqu\'un jour est échangé sans reprendre la garde de la veille.'**
  String custodyDayStartTimeHint(String time);

  /// Réglages du rythme : quand a lieu la transmission.
  ///
  /// In fr, this message translates to:
  /// **'{time} — les enfants changent de foyer à cette heure-là, le dernier jour de chaque période.'**
  String custodyHandoverTimeHint(String time);

  /// No description provided for @custodySwapFrom.
  ///
  /// In fr, this message translates to:
  /// **'Début'**
  String get custodySwapFrom;

  /// No description provided for @custodySwapTo.
  ///
  /// In fr, this message translates to:
  /// **'Fin'**
  String get custodySwapTo;

  /// No description provided for @custodySwapConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Confirmer l\'échange'**
  String get custodySwapConfirm;

  /// Option de créneau : l'échange commence la veille du jour consulté.
  ///
  /// In fr, this message translates to:
  /// **'la veille ({date})'**
  String custodySwapDayBefore(String date);

  /// Option de créneau : la borne tombe le jour consulté.
  ///
  /// In fr, this message translates to:
  /// **'le jour même ({date})'**
  String custodySwapSameDay(String date);

  /// Option de créneau : l'échange finit le lendemain du jour consulté.
  ///
  /// In fr, this message translates to:
  /// **'le lendemain ({date})'**
  String custodySwapDayAfter(String date);

  /// No description provided for @custodyReverted.
  ///
  /// In fr, this message translates to:
  /// **'Échange annulé — le jour revient au rythme habituel.'**
  String get custodyReverted;

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

  /// No description provided for @custodyHolidaysPast.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, one{{count} période passée} other{{count} périodes passées}}'**
  String custodyHolidaysPast(num count);

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

  /// No description provided for @agendaMonthPrevious.
  ///
  /// In fr, this message translates to:
  /// **'Mois précédent'**
  String get agendaMonthPrevious;

  /// No description provided for @agendaMonthNext.
  ///
  /// In fr, this message translates to:
  /// **'Mois suivant'**
  String get agendaMonthNext;

  /// No description provided for @agendaMonthToday.
  ///
  /// In fr, this message translates to:
  /// **'Aujourd\'hui'**
  String get agendaMonthToday;

  /// No description provided for @agendaEmptyMonth.
  ///
  /// In fr, this message translates to:
  /// **'Aucun événement sur {month}.'**
  String agendaEmptyMonth(String month);

  /// No description provided for @agendaCreatedOtherMonth.
  ///
  /// In fr, this message translates to:
  /// **'Créé le {date} — hors du mois affiché.'**
  String agendaCreatedOtherMonth(String date);

  /// No description provided for @agendaDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get agendaDelete;

  /// No description provided for @agendaDeleteConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer cet événement ?'**
  String get agendaDeleteConfirmTitle;

  /// No description provided for @agendaDeleteConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'« {title} » sera supprimé pour tous les membres de la famille.'**
  String agendaDeleteConfirmBody(String title);

  /// No description provided for @agendaDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Événement supprimé.'**
  String get agendaDeleted;

  /// No description provided for @agendaSeriesReadOnly.
  ///
  /// In fr, this message translates to:
  /// **'Cet événement fait partie d\'une série : sa modification et sa suppression par portée se font depuis le web.'**
  String get agendaSeriesReadOnly;

  /// No description provided for @agendaLinkedReadOnly.
  ///
  /// In fr, this message translates to:
  /// **'Événement d\'une famille liée : consultation seule.'**
  String get agendaLinkedReadOnly;

  /// No description provided for @agendaRecurrenceWebOnly.
  ///
  /// In fr, this message translates to:
  /// **'Les événements récurrents se créent depuis le web.'**
  String get agendaRecurrenceWebOnly;

  /// No description provided for @tabExpenses.
  ///
  /// In fr, this message translates to:
  /// **'Dépenses'**
  String get tabExpenses;

  /// No description provided for @expensesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Dépenses partagées'**
  String get expensesTitle;

  /// No description provided for @expensesSubtitle.
  ///
  /// In fr, this message translates to:
  /// **'Frais des enfants · répartition et remboursements'**
  String get expensesSubtitle;

  /// No description provided for @expensesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune dépense pour l\'instant.'**
  String get expensesEmpty;

  /// No description provided for @expensesFilterAll.
  ///
  /// In fr, this message translates to:
  /// **'Toutes'**
  String get expensesFilterAll;

  /// No description provided for @expensesFilterDue.
  ///
  /// In fr, this message translates to:
  /// **'À régler'**
  String get expensesFilterDue;

  /// No description provided for @expensesFilterSettled.
  ///
  /// In fr, this message translates to:
  /// **'Réglées'**
  String get expensesFilterSettled;

  /// No description provided for @expensesNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouvelle dépense'**
  String get expensesNew;

  /// No description provided for @expensesCreate.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer'**
  String get expensesCreate;

  /// No description provided for @expensesCreated.
  ///
  /// In fr, this message translates to:
  /// **'Dépense enregistrée.'**
  String get expensesCreated;

  /// No description provided for @expensesFieldTitle.
  ///
  /// In fr, this message translates to:
  /// **'Libellé'**
  String get expensesFieldTitle;

  /// No description provided for @expensesCategory.
  ///
  /// In fr, this message translates to:
  /// **'Catégorie'**
  String get expensesCategory;

  /// No description provided for @expensesAmount.
  ///
  /// In fr, this message translates to:
  /// **'Montant (€)'**
  String get expensesAmount;

  /// No description provided for @expensesDate.
  ///
  /// In fr, this message translates to:
  /// **'Date'**
  String get expensesDate;

  /// No description provided for @expensesPayer.
  ///
  /// In fr, this message translates to:
  /// **'Payé par'**
  String get expensesPayer;

  /// No description provided for @expensesChildren.
  ///
  /// In fr, this message translates to:
  /// **'Enfant(s) concerné(s)'**
  String get expensesChildren;

  /// No description provided for @expensesNoChild.
  ///
  /// In fr, this message translates to:
  /// **'Ajoutez d\'abord un enfant pour enregistrer une dépense.'**
  String get expensesNoChild;

  /// No description provided for @expensesPaidBy.
  ///
  /// In fr, this message translates to:
  /// **'{payer} a payé'**
  String expensesPaidBy(String payer);

  /// No description provided for @expensesDeletedMember.
  ///
  /// In fr, this message translates to:
  /// **'Membre supprimé'**
  String get expensesDeletedMember;

  /// No description provided for @expensesStatusSettled.
  ///
  /// In fr, this message translates to:
  /// **'Réglée'**
  String get expensesStatusSettled;

  /// No description provided for @expensesStatusDue.
  ///
  /// In fr, this message translates to:
  /// **'À régler'**
  String get expensesStatusDue;

  /// No description provided for @expensesSettle.
  ///
  /// In fr, this message translates to:
  /// **'Marquer réglée'**
  String get expensesSettle;

  /// No description provided for @expensesSettled.
  ///
  /// In fr, this message translates to:
  /// **'Dépense marquée réglée.'**
  String get expensesSettled;

  /// No description provided for @expensesRemind.
  ///
  /// In fr, this message translates to:
  /// **'Relancer'**
  String get expensesRemind;

  /// No description provided for @expensesReminded.
  ///
  /// In fr, this message translates to:
  /// **'Rappel envoyé.'**
  String get expensesReminded;

  /// No description provided for @expensesReminderPending.
  ///
  /// In fr, this message translates to:
  /// **'Un rappel vient d\'être envoyé pour cette dépense.'**
  String get expensesReminderPending;

  /// No description provided for @expensesDelete.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get expensesDelete;

  /// No description provided for @expensesDeleteConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer cette dépense ?'**
  String get expensesDeleteConfirmTitle;

  /// No description provided for @expensesDeleteConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'« {title} » sera supprimée pour tous les membres de la famille.'**
  String expensesDeleteConfirmBody(String title);

  /// No description provided for @expensesDeleted.
  ///
  /// In fr, this message translates to:
  /// **'Dépense supprimée.'**
  String get expensesDeleted;

  /// No description provided for @expensesBalanceTitle.
  ///
  /// In fr, this message translates to:
  /// **'Solde courant'**
  String get expensesBalanceTitle;

  /// No description provided for @expensesBalanced.
  ///
  /// In fr, this message translates to:
  /// **'Comptes équilibrés'**
  String get expensesBalanced;

  /// No description provided for @expensesOwes.
  ///
  /// In fr, this message translates to:
  /// **'{debtor} doit {amount} à {creditor}'**
  String expensesOwes(String debtor, String amount, String creditor);

  /// No description provided for @expensesBalanceExcluded.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, one{{count} dépense est exclue du solde (compte supprimé).} other{{count} dépenses sont exclues du solde (compte supprimé).}}'**
  String expensesBalanceExcluded(num count);

  /// No description provided for @expensesBalanceUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Solde indisponible hors connexion.'**
  String get expensesBalanceUnavailable;

  /// No description provided for @expensesByCategory.
  ///
  /// In fr, this message translates to:
  /// **'Par catégorie'**
  String get expensesByCategory;

  /// No description provided for @expensesTotal.
  ///
  /// In fr, this message translates to:
  /// **'Total engagé'**
  String get expensesTotal;

  /// No description provided for @expensesRemaining.
  ///
  /// In fr, this message translates to:
  /// **'Reste à régler'**
  String get expensesRemaining;

  /// No description provided for @expensesAmountInvalid.
  ///
  /// In fr, this message translates to:
  /// **'Saisissez un montant supérieur à zéro.'**
  String get expensesAmountInvalid;

  /// No description provided for @expensesChildRequired.
  ///
  /// In fr, this message translates to:
  /// **'Sélectionnez au moins un enfant.'**
  String get expensesChildRequired;

  /// No description provided for @expCatHealth.
  ///
  /// In fr, this message translates to:
  /// **'Santé'**
  String get expCatHealth;

  /// No description provided for @expCatSchooling.
  ///
  /// In fr, this message translates to:
  /// **'Scolarité'**
  String get expCatSchooling;

  /// No description provided for @expCatActivities.
  ///
  /// In fr, this message translates to:
  /// **'Activités'**
  String get expCatActivities;

  /// No description provided for @expCatClothing.
  ///
  /// In fr, this message translates to:
  /// **'Vêtements'**
  String get expCatClothing;

  /// No description provided for @expCatOther.
  ///
  /// In fr, this message translates to:
  /// **'Autre'**
  String get expCatOther;

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

  /// No description provided for @exchangesTitle.
  ///
  /// In fr, this message translates to:
  /// **'Échanges'**
  String get exchangesTitle;

  /// No description provided for @exchangesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun échange pour le moment.'**
  String get exchangesEmpty;

  /// No description provided for @exchangesNew.
  ///
  /// In fr, this message translates to:
  /// **'Nouvel échange'**
  String get exchangesNew;

  /// No description provided for @exchangesFilterOpen.
  ///
  /// In fr, this message translates to:
  /// **'Ouverts'**
  String get exchangesFilterOpen;

  /// No description provided for @exchangesFilterDecided.
  ///
  /// In fr, this message translates to:
  /// **'Décidés'**
  String get exchangesFilterDecided;

  /// No description provided for @exchangesOpenCount.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{Aucun échange ouvert} one{{count} échange ouvert} other{{count} échanges ouverts}}'**
  String exchangesOpenCount(num count);

  /// No description provided for @exchangeTypeDiscussion.
  ///
  /// In fr, this message translates to:
  /// **'Discussion'**
  String get exchangeTypeDiscussion;

  /// No description provided for @exchangeTypeDecision.
  ///
  /// In fr, this message translates to:
  /// **'Décision'**
  String get exchangeTypeDecision;

  /// No description provided for @exchangeWholeFamily.
  ///
  /// In fr, this message translates to:
  /// **'Toute la famille'**
  String get exchangeWholeFamily;

  /// No description provided for @exchangeOverdue.
  ///
  /// In fr, this message translates to:
  /// **'En retard'**
  String get exchangeOverdue;

  /// No description provided for @exchangeDecided.
  ///
  /// In fr, this message translates to:
  /// **'Décidé'**
  String get exchangeDecided;

  /// Marqueur de fil non lu (X5) — lu par les lecteurs d'écran, la pastille ne l'étant pas.
  ///
  /// In fr, this message translates to:
  /// **'Non lu'**
  String get exchangeUnread;

  /// Déplie les champs secondaires du formulaire de création (point 8 de la revue).
  ///
  /// In fr, this message translates to:
  /// **'Plus d\'options'**
  String get exchangeMoreOptions;

  /// No description provided for @exchangeSetDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Fixer une échéance'**
  String get exchangeSetDeadline;

  /// No description provided for @exchangeChangeDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Modifier l\'échéance'**
  String get exchangeChangeDeadline;

  /// No description provided for @exchangeClearDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Retirer'**
  String get exchangeClearDeadline;

  /// No description provided for @exchangeDeadlineSaved.
  ///
  /// In fr, this message translates to:
  /// **'Échéance enregistrée.'**
  String get exchangeDeadlineSaved;

  /// No description provided for @exchangeDeadlineCleared.
  ///
  /// In fr, this message translates to:
  /// **'Échéance retirée.'**
  String get exchangeDeadlineCleared;

  /// No description provided for @exchangeAnonymous.
  ///
  /// In fr, this message translates to:
  /// **'Membre supprimé'**
  String get exchangeAnonymous;

  /// No description provided for @exchangeDeadlineLabel.
  ///
  /// In fr, this message translates to:
  /// **'Échéance : {date}'**
  String exchangeDeadlineLabel(String date);

  /// No description provided for @exchangeThread.
  ///
  /// In fr, this message translates to:
  /// **'Fil de discussion'**
  String get exchangeThread;

  /// No description provided for @exchangeThreadEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun message pour l\'instant.'**
  String get exchangeThreadEmpty;

  /// No description provided for @exchangeMessagePlaceholder.
  ///
  /// In fr, this message translates to:
  /// **'Écrire un message…'**
  String get exchangeMessagePlaceholder;

  /// No description provided for @exchangeDecidedBanner.
  ///
  /// In fr, this message translates to:
  /// **'Décision tranchée'**
  String get exchangeDecidedBanner;

  /// No description provided for @exchangeValidateOption.
  ///
  /// In fr, this message translates to:
  /// **'Valider cette option'**
  String get exchangeValidateOption;

  /// No description provided for @exchangeChooseOption.
  ///
  /// In fr, this message translates to:
  /// **'Valider mon choix'**
  String get exchangeChooseOption;

  /// No description provided for @exchangeChangeChoice.
  ///
  /// In fr, this message translates to:
  /// **'Changer mon choix'**
  String get exchangeChangeChoice;

  /// No description provided for @exchangeMyChoice.
  ///
  /// In fr, this message translates to:
  /// **'votre choix'**
  String get exchangeMyChoice;

  /// No description provided for @exchangeSettle.
  ///
  /// In fr, this message translates to:
  /// **'Retenir cette option et clore'**
  String get exchangeSettle;

  /// No description provided for @exchangeSettleConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Trancher la décision ?'**
  String get exchangeSettleConfirmTitle;

  /// No description provided for @exchangeSettleConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'L\'option « {label} » sera retenue et l\'échange clos. Cette action est irréversible.'**
  String exchangeSettleConfirmBody(String label);

  /// No description provided for @exchangeVoteMaySettleTitle.
  ///
  /// In fr, this message translates to:
  /// **'Votre choix peut trancher'**
  String get exchangeVoteMaySettleTitle;

  /// No description provided for @exchangeVoteMaySettleBody.
  ///
  /// In fr, this message translates to:
  /// **'Tous les autres membres ont voté : votre choix peut trancher définitivement cette décision.'**
  String get exchangeVoteMaySettleBody;

  /// No description provided for @exchangeStatusDecided.
  ///
  /// In fr, this message translates to:
  /// **'Décidé : {label}'**
  String exchangeStatusDecided(String label);

  /// No description provided for @exchangeStatusMaySettle.
  ///
  /// In fr, this message translates to:
  /// **'Votre choix peut trancher la décision.'**
  String get exchangeStatusMaySettle;

  /// No description provided for @exchangeStatusAwaiting.
  ///
  /// In fr, this message translates to:
  /// **'En attente de {names}.'**
  String exchangeStatusAwaiting(String names);

  /// No description provided for @exchangeStatusAwaitingEditable.
  ///
  /// In fr, this message translates to:
  /// **'En attente de {names} — votre choix reste modifiable.'**
  String exchangeStatusAwaitingEditable(String names);

  /// No description provided for @exchangeStatusDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Réponse attendue avant le {date}.'**
  String exchangeStatusDeadline(String date);

  /// No description provided for @exchangeVotesCount.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{Aucune validation} one{{count} validation} other{{count} validations}}'**
  String exchangeVotesCount(num count);

  /// No description provided for @exchangeConflict.
  ///
  /// In fr, this message translates to:
  /// **'Cette décision est déjà tranchée.'**
  String get exchangeConflict;

  /// No description provided for @exchangeClose.
  ///
  /// In fr, this message translates to:
  /// **'Clôturer'**
  String get exchangeClose;

  /// No description provided for @exchangeCloseConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Clore définitivement ?'**
  String get exchangeCloseConfirmTitle;

  /// No description provided for @exchangeCloseConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'L\'échange « {title} » sera clos et ne pourra plus être rouvert. Les membres concernés en seront informés.'**
  String exchangeCloseConfirmBody(String title);

  /// No description provided for @exchangeClosed.
  ///
  /// In fr, this message translates to:
  /// **'Échange clôturé.'**
  String get exchangeClosed;

  /// No description provided for @exchangeCreate.
  ///
  /// In fr, this message translates to:
  /// **'Créer'**
  String get exchangeCreate;

  /// No description provided for @exchangeCreated.
  ///
  /// In fr, this message translates to:
  /// **'Échange créé.'**
  String get exchangeCreated;

  /// No description provided for @exchangeFieldTitle.
  ///
  /// In fr, this message translates to:
  /// **'Titre'**
  String get exchangeFieldTitle;

  /// No description provided for @exchangeFieldBody.
  ///
  /// In fr, this message translates to:
  /// **'Message ou contexte (optionnel)'**
  String get exchangeFieldBody;

  /// No description provided for @exchangeChooseChild.
  ///
  /// In fr, this message translates to:
  /// **'Enfant concerné'**
  String get exchangeChooseChild;

  /// No description provided for @exchangeFieldDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Échéance (optionnelle)'**
  String get exchangeFieldDeadline;

  /// No description provided for @exchangeNoDeadline.
  ///
  /// In fr, this message translates to:
  /// **'Aucune échéance'**
  String get exchangeNoDeadline;

  /// No description provided for @exchangeFieldOptions.
  ///
  /// In fr, this message translates to:
  /// **'Options'**
  String get exchangeFieldOptions;

  /// No description provided for @exchangeOptionLabel.
  ///
  /// In fr, this message translates to:
  /// **'Option'**
  String get exchangeOptionLabel;

  /// No description provided for @exchangeAddOption.
  ///
  /// In fr, this message translates to:
  /// **'Ajouter une option'**
  String get exchangeAddOption;

  /// No description provided for @exchangeOptionsHint.
  ///
  /// In fr, this message translates to:
  /// **'Une décision requiert au moins deux options.'**
  String get exchangeOptionsHint;

  /// No description provided for @notificationsTitle.
  ///
  /// In fr, this message translates to:
  /// **'Notifications'**
  String get notificationsTitle;

  /// No description provided for @notificationsEmailSection.
  ///
  /// In fr, this message translates to:
  /// **'Notifications e-mail'**
  String get notificationsEmailSection;

  /// No description provided for @notifCategoryCustody.
  ///
  /// In fr, this message translates to:
  /// **'Garde'**
  String get notifCategoryCustody;

  /// No description provided for @notifCategoryAgenda.
  ///
  /// In fr, this message translates to:
  /// **'Agenda'**
  String get notifCategoryAgenda;

  /// No description provided for @notifCategoryExchanges.
  ///
  /// In fr, this message translates to:
  /// **'Échanges'**
  String get notifCategoryExchanges;

  /// No description provided for @notifCategoryInvitation.
  ///
  /// In fr, this message translates to:
  /// **'Invitations'**
  String get notifCategoryInvitation;

  /// No description provided for @notifCategoryExpenses.
  ///
  /// In fr, this message translates to:
  /// **'Dépenses'**
  String get notifCategoryExpenses;

  /// No description provided for @notificationsPushSection.
  ///
  /// In fr, this message translates to:
  /// **'Notifications sur ce téléphone'**
  String get notificationsPushSection;

  /// No description provided for @notificationsPushHint.
  ///
  /// In fr, this message translates to:
  /// **'Recevez une notification même application fermée, pour les sujets choisis.'**
  String get notificationsPushHint;

  /// No description provided for @notificationsPushAsk.
  ///
  /// In fr, this message translates to:
  /// **'Activer un sujet demandera au téléphone l\'autorisation d\'afficher des notifications.'**
  String get notificationsPushAsk;

  /// No description provided for @notificationsPushDenied.
  ///
  /// In fr, this message translates to:
  /// **'Les notifications de CoParentPlanner sont désactivées dans les réglages du téléphone.'**
  String get notificationsPushDenied;

  /// No description provided for @notificationsPushOpenSettings.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir les réglages'**
  String get notificationsPushOpenSettings;

  /// No description provided for @notificationsPushUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Les notifications push ne sont pas disponibles sur cet appareil.'**
  String get notificationsPushUnavailable;

  /// No description provided for @notificationsDevicesSection.
  ///
  /// In fr, this message translates to:
  /// **'Appareils'**
  String get notificationsDevicesSection;

  /// No description provided for @notificationsDevicesHint.
  ///
  /// In fr, this message translates to:
  /// **'Ces appareils reçoivent vos notifications push. Retirez celui que vous n\'utilisez plus ou que vous avez perdu.'**
  String get notificationsDevicesHint;

  /// No description provided for @notificationsDevicesEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucun appareil ne reçoit vos notifications push.'**
  String get notificationsDevicesEmpty;

  /// No description provided for @notificationsDeviceThis.
  ///
  /// In fr, this message translates to:
  /// **'Cet appareil'**
  String get notificationsDeviceThis;

  /// No description provided for @notificationsDeviceIos.
  ///
  /// In fr, this message translates to:
  /// **'iPhone ou iPad'**
  String get notificationsDeviceIos;

  /// No description provided for @notificationsDeviceAndroid.
  ///
  /// In fr, this message translates to:
  /// **'Appareil Android'**
  String get notificationsDeviceAndroid;

  /// No description provided for @notificationsDeviceLastSeen.
  ///
  /// In fr, this message translates to:
  /// **'Dernière activité : {date}'**
  String notificationsDeviceLastSeen(String date);

  /// No description provided for @notificationsDeviceRevoke.
  ///
  /// In fr, this message translates to:
  /// **'Retirer'**
  String get notificationsDeviceRevoke;

  /// No description provided for @notificationsDeviceRevokeTitle.
  ///
  /// In fr, this message translates to:
  /// **'Retirer cet appareil ?'**
  String get notificationsDeviceRevokeTitle;

  /// No description provided for @notificationsDeviceRevokeBody.
  ///
  /// In fr, this message translates to:
  /// **'Il ne recevra plus de notification push. Il suffira de se reconnecter dessus pour le réactiver.'**
  String get notificationsDeviceRevokeBody;

  /// No description provided for @notificationsDeviceRevoked.
  ///
  /// In fr, this message translates to:
  /// **'Appareil retiré.'**
  String get notificationsDeviceRevoked;

  /// No description provided for @pushOpen.
  ///
  /// In fr, this message translates to:
  /// **'Ouvrir'**
  String get pushOpen;

  /// No description provided for @pushTargetUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Ce contenu n\'est plus accessible depuis votre compte.'**
  String get pushTargetUnavailable;

  /// No description provided for @notificationsQuietSection.
  ///
  /// In fr, this message translates to:
  /// **'Heures silencieuses'**
  String get notificationsQuietSection;

  /// No description provided for @notificationsQuietHint.
  ///
  /// In fr, this message translates to:
  /// **'Ni e-mail ni notification push pendant cette plage (fuseau de la famille) : ils partent à la fin.'**
  String get notificationsQuietHint;

  /// No description provided for @notificationsQuietStart.
  ///
  /// In fr, this message translates to:
  /// **'Début'**
  String get notificationsQuietStart;

  /// No description provided for @notificationsQuietEnd.
  ///
  /// In fr, this message translates to:
  /// **'Fin'**
  String get notificationsQuietEnd;

  /// No description provided for @notificationsQuietUnset.
  ///
  /// In fr, this message translates to:
  /// **'Non définie'**
  String get notificationsQuietUnset;

  /// No description provided for @notificationsQuietSave.
  ///
  /// In fr, this message translates to:
  /// **'Enregistrer la plage'**
  String get notificationsQuietSave;

  /// No description provided for @notificationsQuietSaved.
  ///
  /// In fr, this message translates to:
  /// **'Heures silencieuses enregistrées.'**
  String get notificationsQuietSaved;

  /// No description provided for @offlineBanner.
  ///
  /// In fr, this message translates to:
  /// **'Hors-ligne — affichage des données mises en cache'**
  String get offlineBanner;

  /// No description provided for @offlineUnavailable.
  ///
  /// In fr, this message translates to:
  /// **'Données non disponibles hors-ligne'**
  String get offlineUnavailable;

  /// No description provided for @offlineQueued.
  ///
  /// In fr, this message translates to:
  /// **'Enregistré — en attente de synchro'**
  String get offlineQueued;

  /// No description provided for @syncPendingBadge.
  ///
  /// In fr, this message translates to:
  /// **'En attente'**
  String get syncPendingBadge;

  /// No description provided for @agendaPendingSyncHelp.
  ///
  /// In fr, this message translates to:
  /// **'Cet événement est enregistré sur cet appareil et sera envoyé au retour du réseau. Il n\'existe pas encore côté serveur.'**
  String get agendaPendingSyncHelp;

  /// No description provided for @syncPending.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{Tout est synchronisé} =1{1 modification en attente de synchro} other{{count} modifications en attente de synchro}}'**
  String syncPending(int count);

  /// No description provided for @syncRejected.
  ///
  /// In fr, this message translates to:
  /// **'{count, plural, =0{} =1{1 modification refusée} other{{count} modifications refusées}}'**
  String syncRejected(int count);

  /// No description provided for @syncRejectedTitle.
  ///
  /// In fr, this message translates to:
  /// **'Modifications refusées'**
  String get syncRejectedTitle;

  /// No description provided for @syncRejectedHelp.
  ///
  /// In fr, this message translates to:
  /// **'Le serveur a refusé ces modifications enregistrées hors-ligne. Relancez-les ou abandonnez-les.'**
  String get syncRejectedHelp;

  /// No description provided for @syncRejectedReason.
  ///
  /// In fr, this message translates to:
  /// **'{reason, select, conflict{Conflit : la donnée a changé entre-temps} forbidden{Droits insuffisants} invalid{Contenu refusé par le serveur} gone{L\'élément n\'existe plus} other{Refusé par le serveur}}'**
  String syncRejectedReason(String reason);

  /// No description provided for @syncRejectedAction.
  ///
  /// In fr, this message translates to:
  /// **'Voir'**
  String get syncRejectedAction;

  /// No description provided for @actionDiscard.
  ///
  /// In fr, this message translates to:
  /// **'Abandonner'**
  String get actionDiscard;

  /// No description provided for @syncRejectedEmpty.
  ///
  /// In fr, this message translates to:
  /// **'Aucune modification refusée.'**
  String get syncRejectedEmpty;

  /// No description provided for @privacyTitle.
  ///
  /// In fr, this message translates to:
  /// **'Confidentialité & données'**
  String get privacyTitle;

  /// No description provided for @privacyExportSection.
  ///
  /// In fr, this message translates to:
  /// **'Exporter mes données'**
  String get privacyExportSection;

  /// No description provided for @privacyExportHint.
  ///
  /// In fr, this message translates to:
  /// **'Recevez une archive de vos données personnelles (droit d\'accès et de portabilité).'**
  String get privacyExportHint;

  /// No description provided for @privacyExportButton.
  ///
  /// In fr, this message translates to:
  /// **'Demander l\'export'**
  String get privacyExportButton;

  /// No description provided for @privacyExportPending.
  ///
  /// In fr, this message translates to:
  /// **'Export en cours de préparation…'**
  String get privacyExportPending;

  /// No description provided for @privacyExportRefresh.
  ///
  /// In fr, this message translates to:
  /// **'Actualiser le statut'**
  String get privacyExportRefresh;

  /// No description provided for @privacyExportReady.
  ///
  /// In fr, this message translates to:
  /// **'Votre export est prêt (lien temporaire) :'**
  String get privacyExportReady;

  /// No description provided for @privacyDeleteSection.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer mon compte'**
  String get privacyDeleteSection;

  /// No description provided for @privacyDeleteHint.
  ///
  /// In fr, this message translates to:
  /// **'Vos données personnelles sont anonymisées de façon irréversible. Vos messages et notes sont conservés sous « Membre supprimé » pour préserver les fils.'**
  String get privacyDeleteHint;

  /// No description provided for @privacyDeleteButton.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer mon compte'**
  String get privacyDeleteButton;

  /// No description provided for @privacyDeleteConfirmTitle.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer définitivement ?'**
  String get privacyDeleteConfirmTitle;

  /// No description provided for @privacyDeleteConfirmBody.
  ///
  /// In fr, this message translates to:
  /// **'Cette action est irréversible : votre compte sera anonymisé et vous serez déconnecté.'**
  String get privacyDeleteConfirmBody;

  /// No description provided for @privacyDeleteConfirm.
  ///
  /// In fr, this message translates to:
  /// **'Supprimer'**
  String get privacyDeleteConfirm;
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
