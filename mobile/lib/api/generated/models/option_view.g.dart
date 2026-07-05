// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'option_view.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

OptionView _$OptionViewFromJson(Map<String, dynamic> json) => OptionView(
  sub: json['sub'] as String?,
  id: json['id'] as String? ?? '',
  label: json['label'] as String? ?? '',
  position: (json['position'] as num?)?.toInt() ?? 0,
  voteCount: (json['voteCount'] as num?)?.toInt() ?? 0,
  decided: json['decided'] as bool? ?? false,
);

Map<String, dynamic> _$OptionViewToJson(OptionView instance) =>
    <String, dynamic>{
      'id': instance.id,
      'label': instance.label,
      'sub': instance.sub,
      'position': instance.position,
      'voteCount': instance.voteCount,
      'decided': instance.decided,
    };
