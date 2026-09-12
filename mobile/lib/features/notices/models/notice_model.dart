import 'package:equatable/equatable.dart';

class NoticeModel extends Equatable {
  final int id;
  final String title;
  final String content;
  final bool isPinned;
  final String publishDate;
  final String? expiresAt;
  final String createdAt;

  const NoticeModel({
    required this.id,
    required this.title,
    required this.content,
    required this.isPinned,
    required this.publishDate,
    this.expiresAt,
    required this.createdAt,
  });

  factory NoticeModel.fromJson(Map<String, dynamic> json) {
    return NoticeModel(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      content: json['content'] as String? ?? '',
      isPinned: json['is_pinned'] as bool? ?? false,
      publishDate: json['publish_date'] as String? ?? '',
      expiresAt: json['expires_at'] as String?,
      createdAt: json['created_at'] as String? ?? '',
    );
  }

  @override
  List<Object?> get props => [id, title, content, isPinned, publishDate, expiresAt, createdAt];
}
