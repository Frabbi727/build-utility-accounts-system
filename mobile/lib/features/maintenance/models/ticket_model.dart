import 'package:equatable/equatable.dart';

class MaintenanceTicketModel extends Equatable {
  final int id;
  final int flatId;
  final String category;
  final String title;
  final String description;
  final String priority; // 'low' | 'medium' | 'high' | 'urgent'
  final String status; // 'pending' | 'in_progress' | 'resolved' | 'cancelled'
  final String? assignedToName;
  final String createdAt;
  final String? resolvedAt;

  const MaintenanceTicketModel({
    required this.id,
    required this.flatId,
    required this.category,
    required this.title,
    required this.description,
    required this.priority,
    required this.status,
    this.assignedToName,
    required this.createdAt,
    this.resolvedAt,
  });

  factory MaintenanceTicketModel.fromJson(Map<String, dynamic> json) {
    return MaintenanceTicketModel(
      id: json['id'] as int,
      flatId: json['flat_id'] as int? ?? 0,
      category: json['category'] as String? ?? 'General',
      title: json['title'] as String? ?? '',
      description: json['description'] as String? ?? '',
      priority: json['priority'] as String? ?? 'medium',
      status: json['status'] as String? ?? 'pending',
      assignedToName: json['assigned_to_name'] as String?,
      createdAt: json['created_at'] as String? ?? '',
      resolvedAt: json['resolved_at'] as String?,
    );
  }

  bool get isPending => status == 'pending';
  bool get isInProgress => status == 'in_progress';
  bool get isResolved => status == 'resolved';

  @override
  List<Object?> get props => [
        id,
        flatId,
        category,
        title,
        description,
        priority,
        status,
        assignedToName,
        createdAt,
        resolvedAt,
      ];
}
