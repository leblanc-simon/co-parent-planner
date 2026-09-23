import Flutter
import UIKit

@main
@objc class AppDelegate: FlutterAppDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {
    GeneratedPluginRegistrant.register(with: self)

    // Push (Lot 11) : une permission de notification refusée ne se rend que dans les
    // réglages du système — l'écran Notifications y mène par ce canal.
    if let controller = window?.rootViewController as? FlutterViewController {
      FlutterMethodChannel(
        name: "coparentplanner/system_settings",
        binaryMessenger: controller.binaryMessenger
      ).setMethodCallHandler { call, result in
        guard call.method == "openNotificationSettings" else {
          result(FlutterMethodNotImplemented)
          return
        }
        let target: String
        if #available(iOS 16.0, *) {
          target = UIApplication.openNotificationSettingsURLString
        } else {
          target = UIApplication.openSettingsURLString
        }
        if let url = URL(string: target) {
          UIApplication.shared.open(url)
        }
        result(nil)
      }
    }
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }
}
