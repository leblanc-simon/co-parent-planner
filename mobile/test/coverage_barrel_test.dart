// ATTENTION — fichier tenu à jour par un test qui se vérifie lui-même.
//
// `flutter test --coverage` n'instrumente que les bibliothèques **réellement
// chargées** par l'isolat de test : un fichier qu'aucun test n'importe est absent
// de `lcov.info`, donc absent du **dénominateur** de la porte de couverture. Une
// fonctionnalité entière livrée sans le moindre test ne faisait donc pas bouger le
// taux affiché (revue mobile 2026-08, constat C68).
//
// Ce fichier importe toute la bibliothèque pour que chaque ligne pèse au
// dénominateur, et vérifie lui-même que la liste est complète : ajouter un fichier
// à `lib/` sans l'importer ici fait échouer le test, avec la ligne à ajouter.
//
// `tool/check_coverage.py` applique la même règle du côté du rapport.
//
// Les imports ne sont volontairement pas utilisés : les charger suffit à les faire
// instrumenter.
// ignore_for_file: unused_import

import 'dart:io';

import 'package:coparentplanner/app.dart';
import 'package:coparentplanner/core/config/app_config.dart';
import 'package:coparentplanner/core/deeplink/deep_link_handler.dart';
import 'package:coparentplanner/core/error/api_exception.dart';
import 'package:coparentplanner/core/error/error_text.dart';
import 'package:coparentplanner/core/i18n/locale_controller.dart';
import 'package:coparentplanner/core/network/api_clients.dart';
import 'package:coparentplanner/core/network/interceptors/api_routes.dart';
import 'package:coparentplanner/core/network/interceptors/auth_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/family_scope_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/idempotency_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/locale_interceptor.dart';
import 'package:coparentplanner/core/network/interceptors/retry_interceptor.dart';
import 'package:coparentplanner/core/network/network_providers.dart';
import 'package:coparentplanner/core/session/session_scope.dart';
import 'package:coparentplanner/core/storage/drift/daos/cache_dao.dart';
import 'package:coparentplanner/core/storage/drift/daos/outbox_dao.dart';
import 'package:coparentplanner/core/storage/drift/database.dart';
import 'package:coparentplanner/core/storage/drift/tables/cache_entry.dart';
import 'package:coparentplanner/core/storage/drift/tables/outbox_entry.dart';
import 'package:coparentplanner/core/storage/local_data_purger.dart';
import 'package:coparentplanner/core/storage/secure_storage.dart';
import 'package:coparentplanner/core/storage/storage_providers.dart';
import 'package:coparentplanner/core/sync/connectivity_provider.dart';
import 'package:coparentplanner/core/sync/idempotency_scope.dart';
import 'package:coparentplanner/core/sync/outbox_queued_exception.dart';
import 'package:coparentplanner/core/sync/outbox_service.dart';
import 'package:coparentplanner/core/sync/outbox_status.dart';
import 'package:coparentplanner/core/realtime/realtime_client.dart';
import 'package:coparentplanner/core/realtime/sse.dart';
import 'package:coparentplanner/core/sync/pending_local.dart';
import 'package:coparentplanner/core/sync/read_cache.dart';
import 'package:coparentplanner/core/sync/sync_providers.dart';
import 'package:coparentplanner/core/theme/app_colors.dart';
import 'package:coparentplanner/core/theme/app_shapes.dart';
import 'package:coparentplanner/core/theme/app_theme.dart';
import 'package:coparentplanner/core/theme/app_typography.dart';
import 'package:coparentplanner/core/theme/button_styles.dart';
import 'package:coparentplanner/core/theme/liquid_glass.dart';
import 'package:coparentplanner/core/util/civil_date.dart';
import 'package:coparentplanner/core/util/date_labels.dart';
import 'package:coparentplanner/core/util/detached_providers.dart';
import 'package:coparentplanner/core/util/money_labels.dart';
import 'package:coparentplanner/core/widgets/app_filter_chip.dart';
import 'package:coparentplanner/core/widgets/confirm_dialog.dart';
import 'package:coparentplanner/core/widgets/error_banner.dart';
import 'package:coparentplanner/core/widgets/initial_avatar.dart';
import 'package:coparentplanner/core/widgets/large_title_header.dart';
import 'package:coparentplanner/core/widgets/offline_banner.dart';
import 'package:coparentplanner/features/agenda/data/agenda_repository.dart';
import 'package:coparentplanner/features/agenda/data/overlay_repository.dart';
import 'package:coparentplanner/features/agenda/data/pending_events.dart';
import 'package:coparentplanner/features/agenda/presentation/agenda_screen.dart';
import 'package:coparentplanner/features/auth/application/auth_controller.dart';
import 'package:coparentplanner/features/auth/application/auth_state.dart';
import 'package:coparentplanner/features/auth/data/auth_repository.dart';
import 'package:coparentplanner/features/auth/presentation/check_email_screen.dart';
import 'package:coparentplanner/features/auth/presentation/forgot_password_screen.dart';
import 'package:coparentplanner/features/auth/presentation/login_screen.dart';
import 'package:coparentplanner/features/auth/presentation/register_screen.dart';
import 'package:coparentplanner/features/auth/presentation/reset_password_screen.dart';
import 'package:coparentplanner/features/auth/presentation/splash_screen.dart';
import 'package:coparentplanner/features/auth/presentation/verify_email_screen.dart';
import 'package:coparentplanner/features/auth/presentation/widgets/auth_scaffold.dart';
import 'package:coparentplanner/features/children/data/children_repository.dart';
import 'package:coparentplanner/features/children/presentation/child_detail_screen.dart';
import 'package:coparentplanner/features/children/presentation/children_screen.dart';
import 'package:coparentplanner/features/custody/application/current_custody.dart';
import 'package:coparentplanner/features/custody/application/custody_palette.dart';
import 'package:coparentplanner/features/custody/application/day_detail.dart';
import 'package:coparentplanner/features/custody/application/day_segments.dart';
import 'package:coparentplanner/features/custody/application/swap_defaults.dart';
import 'package:coparentplanner/features/custody/data/custody_repository.dart';
import 'package:coparentplanner/features/custody/presentation/custody_activities.dart';
import 'package:coparentplanner/features/custody/presentation/custody_screen.dart';
import 'package:coparentplanner/features/custody/presentation/custody_settings_screen.dart';
import 'package:coparentplanner/features/custody/presentation/parent_initials.dart';
import 'package:coparentplanner/features/depenses/data/expense_repository.dart';
import 'package:coparentplanner/features/depenses/data/pending_expenses.dart';
import 'package:coparentplanner/features/depenses/presentation/depenses_screen.dart';
import 'package:coparentplanner/features/exchanges/application/exchanges_realtime.dart';
import 'package:coparentplanner/features/exchanges/data/exchanges_repository.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchange_detail_screen.dart';
import 'package:coparentplanner/features/exchanges/presentation/exchanges_screen.dart';
import 'package:coparentplanner/features/family/application/family_context.dart';
import 'package:coparentplanner/features/family/data/family_repository.dart';
import 'package:coparentplanner/features/family/presentation/family_switcher.dart';
import 'package:coparentplanner/features/home/shell/agenda_tab.dart';
import 'package:coparentplanner/features/home/shell/depenses_tab.dart';
import 'package:coparentplanner/features/home/shell/home_shell.dart';
import 'package:coparentplanner/features/home/shell/home_tab.dart';
import 'package:coparentplanner/features/home/shell/settings_tab.dart';
import 'package:coparentplanner/features/members/data/members_repository.dart';
import 'package:coparentplanner/features/members/presentation/invite_screen.dart';
import 'package:coparentplanner/features/members/presentation/members_screen.dart';
import 'package:coparentplanner/features/notifications/data/notification_prefs_repository.dart';
import 'package:coparentplanner/features/notifications/presentation/notifications_screen.dart';
import 'package:coparentplanner/features/onboarding/application/onboarding_controller.dart';
import 'package:coparentplanner/features/onboarding/presentation/create_family_screen.dart';
import 'package:coparentplanner/features/onboarding/presentation/join_family_screen.dart';
import 'package:coparentplanner/features/onboarding/presentation/onboarding_screen.dart';
import 'package:coparentplanner/features/settings/data/rgpd_repository.dart';
import 'package:coparentplanner/features/settings/presentation/privacy_screen.dart';
import 'package:coparentplanner/main.dart';
import 'package:coparentplanner/routing/app_router.dart';
import 'package:coparentplanner/routing/routes.dart';

import 'package:flutter_test/flutter_test.dart';

/// Mêmes exclusions que `tool/check_coverage.py` : code généré et catalogues.
bool _excluded(String path) =>
    path.contains('/api/generated/') ||
    path.endsWith('.g.dart') ||
    path.endsWith('.freezed.dart') ||
    path.contains('l10n/app_localizations');

void main() {
  test('tout `lib/` est importé ici, donc compté au dénominateur', () {
    final onDisk =
        Directory('lib')
            .listSync(recursive: true)
            .whereType<File>()
            .map((f) => f.path.replaceAll(r'\\', '/'))
            .where((p) => p.endsWith('.dart') && !_excluded(p))
            .map((p) => "import 'package:coparentplanner/${p.substring(4)}';")
            .toList()
          ..sort();

    final imported = File('test/coverage_barrel_test.dart')
        .readAsLinesSync()
        .where((l) => l.startsWith("import 'package:coparentplanner/"))
        .toList();

    expect(
      onDisk.toSet().difference(imported.toSet()),
      isEmpty,
      reason:
          'Fichiers de lib/ absents du barrel : ajoutez les lignes ci-dessus à '
          'test/coverage_barrel_test.dart pour qu\'ils comptent dans la couverture.',
    );
    expect(
      imported.toSet().difference(onDisk.toSet()),
      isEmpty,
      reason: 'Imports du barrel pointant vers des fichiers supprimés.',
    );
  });
}
