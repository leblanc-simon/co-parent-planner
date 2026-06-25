import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_shapes.dart';

/// Banniere d'erreur (RFC 7807 / validation), style design system.
class ErrorBanner extends StatelessWidget {
  const ErrorBanner(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.catHealthSoft,
        borderRadius: BorderRadius.circular(AppRadii.field),
        border: Border.all(color: AppColors.error.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.error, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppColors.ink, fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}

/// Banniere d'information / succes (ex. message anti-enumeration).
class InfoBanner extends StatelessWidget {
  const InfoBanner(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.positiveSoft,
        borderRadius: BorderRadius.circular(AppRadii.field),
        border: Border.all(color: AppColors.positive.withValues(alpha: 0.4)),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.check_circle_outline,
            color: AppColors.positiveStrong,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(color: AppColors.ink, fontSize: 14),
            ),
          ),
        ],
      ),
    );
  }
}
