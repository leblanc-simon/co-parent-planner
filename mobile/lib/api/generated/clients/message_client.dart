// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:dio/dio.dart';
import 'package:retrofit/retrofit.dart';

import '../models/message.dart';
import '../models/message_create_message_request.dart';

part 'message_client.g.dart';

@RestApi()
abstract class MessageClient {
  factory MessageClient(Dio dio, {String? baseUrl}) = _MessageClient;

  /// Creates a Message resource.
  ///
  /// Creates a Message resource.
  ///
  /// [exchangeId] - ExchangeResource identifier.
  @POST('/api/exchanges/{exchangeId}/messages')
  Future<Message> apiExchangesExchangeIdmessagesPost({
    @Path('exchangeId') required String exchangeId,
    @Body() required MessageCreateMessageRequest body,
  });
}
