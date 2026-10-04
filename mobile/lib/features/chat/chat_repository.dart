import 'package:dio/dio.dart';
import 'package:fandoogh_crm/core/network/api_client.dart';
import 'package:fandoogh_crm/core/network/api_repository.dart';
import 'package:fandoogh_crm/core/network/paged_result.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final chatRepositoryProvider = Provider<ChatRepository>(
  (ref) => ChatRepository(ref.watch(apiClientProvider)),
);

final class Conversation {
  Conversation.fromJson(Map<String, dynamic> json)
    : id = (json['id'] as num).toInt(),
      listingId = (json['listing_id'] as num).toInt(),
      title = '${json['title'] ?? ''}',
      canSend = json['can_send'] == true,
      unreadCount = (json['unread_count'] as num?)?.toInt() ?? 0;
  final int id, listingId, unreadCount;
  final String title;
  final bool canSend;
}

final class ChatMessage {
  ChatMessage.fromJson(Map<String, dynamic> json)
    : id = (json['id'] as num).toInt(),
      body = '${json['body'] ?? ''}',
      isMine = json['is_mine'] == true,
      imageUrl = json['image_url'] as String?,
      createdAt = '${json['created_at'] ?? ''}';
  final int id;
  final String body, createdAt;
  final bool isMine;
  final String? imageUrl;
}

final class ChatRepository extends ApiRepository {
  const ChatRepository(super.client);
  Future<PagedResult<Conversation>> conversations({int page = 1}) => getPage(
    '/marketplace/conversations',
    query: {'page': page},
    decode: Conversation.fromJson,
  );
  Future<Conversation> start(int listing, String audience) async =>
      Conversation.fromJson(
        await post('/marketplace/listings/$listing/conversations', {
          'audience': audience,
        }),
      );
  Future<PagedResult<ChatMessage>> messages(
    int conversation, {
    int page = 1,
    int? afterId,
  }) => getPage(
    '/marketplace/conversations/$conversation/messages',
    query: {'page': page, 'after_id': afterId},
    decode: ChatMessage.fromJson,
  );
  Future<void> read(int conversation, int through) async {
    await post('/marketplace/conversations/$conversation/read', {
      'through_message_id': through,
    });
  }

  Future<ChatMessage> send(
    int conversation, {
    required String requestId,
    String? body,
    String? imagePath,
  }) async {
    final values = <String, Object?>{
      'client_message_id': requestId,
      'body': body?.trim(),
    };
    if (imagePath != null) {
      values['image'] = await MultipartFile.fromFile(imagePath);
    }
    final response = await client.dio.post<Map<String, dynamic>>(
      '/marketplace/conversations/$conversation/messages',
      data: imagePath == null ? values : FormData.fromMap(values),
    );
    return ChatMessage.fromJson(ApiRepository.unwrapData(response.data));
  }
}
