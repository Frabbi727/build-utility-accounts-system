import 'package:equatable/equatable.dart';
import '../models/ticket_model.dart';

abstract class MaintenanceState extends Equatable {
  const MaintenanceState();

  @override
  List<Object?> get props => [];
}

class MaintenanceInitial extends MaintenanceState {}

class MaintenanceLoading extends MaintenanceState {}

class MaintenanceSubmitting extends MaintenanceState {}

class MaintenanceLoaded extends MaintenanceState {
  final List<MaintenanceTicketModel> tickets;

  const MaintenanceLoaded(this.tickets);

  @override
  List<Object?> get props => [tickets];
}

class MaintenanceCreateSuccess extends MaintenanceState {
  final MaintenanceTicketModel ticket;

  const MaintenanceCreateSuccess(this.ticket);

  @override
  List<Object?> get props => [ticket];
}

class MaintenanceError extends MaintenanceState {
  final String message;

  const MaintenanceError(this.message);

  @override
  List<Object?> get props => [message];
}
