package eu.coparentplanner.app

import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        // Push (Lot 11) : une permission de notification refusée ne se rend que dans les
        // réglages du système — l'écran Notifications y mène par ce canal.
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "coparentplanner/system_settings")
            .setMethodCallHandler { call, result ->
                if (call.method != "openNotificationSettings") {
                    result.notImplemented()
                    return@setMethodCallHandler
                }
                val intent = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                        .putExtra(Settings.EXTRA_APP_PACKAGE, packageName)
                } else {
                    Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS, Uri.fromParts("package", packageName, null))
                }
                startActivity(intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK))
                result.success(null)
            }
    }
}
