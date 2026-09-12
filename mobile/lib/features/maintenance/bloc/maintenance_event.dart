import 'package:equatable/equatable.dart';

abstract class MaintenanceEvent extends Equatable {
  const MaintenanceEvent();

  @override
  List<Object?> get props => [];
}

class MaintenanceFetchRequested extends MaintenanceEvent {
  final int? flatId;

  const MaintenanceFetchRequested({this.flatId});

  @override
  List<Object?> get props => [flatId];
}

class MaintenanceCreateRequested extends MaintenanceEvent {
  final int flatId;
  final String category;
  final String title;
  final String description;
  final String priority;

  const MaintenanceCreateRequested({
    required this.flatId,
    required this.category,
    required this.title,
    required this.description,
    required this.priority,
  });

  @override
  List<Object?> get props => [flatId, category, title, description, priority];
}
